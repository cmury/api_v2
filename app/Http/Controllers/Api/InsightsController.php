<?php

namespace App\Http\Controllers\Api;

use App\Ai\Agents\InsightsAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Insights\AskInsightRequest;
use App\Http\Resources\ChatThreadResource;
use App\Models\ChatThread;
use App\Support\Insights\AnswerComposer;
use App\Support\Insights\InsightsDirectResolver;
use App\Support\Insights\InsightsPromptContext;
use App\Support\Insights\QuestionIntent;
use App\Support\InsightsWarehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\ToolResult;
use Throwable;

class InsightsController extends Controller
{
    /**
     * Max result rows persisted in a message payload (full set is still returned when present).
     */
    private const SNAPSHOT_ROWS = 50;

    public function __construct(
        private readonly InsightsWarehouse $warehouse = new InsightsWarehouse,
        private readonly InsightsDirectResolver $directResolver = new InsightsDirectResolver,
    ) {}

    /**
     * List the authenticated user's chat threads (most recent first).
     */
    public function threads(Request $request): JsonResponse
    {
        $threads = ChatThread::query()
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->limit(100)
            ->get();

        return ChatThreadResource::collection($threads)->response();
    }

    /**
     * Show a single thread with its messages (ownership enforced).
     */
    public function thread(Request $request, ChatThread $thread): JsonResponse
    {
        $this->authorizeThread($request, $thread);

        $thread->load(['messages' => fn ($q) => $q->oldest('id')]);

        return ChatThreadResource::make($thread)->response();
    }

    /**
     * Delete a thread (and, via cascade, its messages).
     */
    public function destroyThread(Request $request, ChatThread $thread): JsonResponse
    {
        $this->authorizeThread($request, $thread);

        $thread->delete();

        return response()->json(['message' => 'thread_deleted']);
    }

    /**
     * Answer a natural-language question via tool-calling agent and persist the exchange.
     */
    public function ask(AskInsightRequest $request): JsonResponse
    {
        $data = $request->validated();
        $question = (string) $data['question'];

        $thread = $this->resolveThread($request, $data['thread_id'] ?? null, $question);

        $history = $thread->messages()
            ->oldest('id')
            ->limit(12)
            ->get(['role', 'content', 'sql', 'payload'])
            ->map(fn ($m) => [
                'role' => $m->role,
                'content' => (string) $m->content,
                'sql' => $m->sql,
                'payload' => $m->payload,
            ])
            ->all();

        $thread->messages()->create([
            'role' => 'user',
            'content' => $question,
        ]);

        if (! $this->warehouse->hasData()) {
            return $this->fail($thread, 'The insights warehouse has no data yet. Load the shared data tables before asking questions.', [], 503);
        }

        $direct = $this->directResolver->try($question);
        if ($direct !== null) {
            return $this->persistAnswer($thread, $question, $direct, rawAnswer: null);
        }

        $prompt = InsightsPromptContext::enrich($question, $history);
        $provider = config('imby.insights_provider', config('ai.default'));
        $model = config('imby.insights_model');
        $timeout = (int) config('imby.insights_timeout', 180);

        try {
            Log::info('Insights agent invoking', [
                'provider' => $provider,
                'model' => $model,
                'follow_up' => $prompt !== $question,
            ]);

            $response = (new InsightsAgent($history))->prompt(
                $prompt,
                provider: $provider,
                model: is_string($model) && $model !== '' ? $model : null,
                timeout: $timeout,
            );
        } catch (Throwable $e) {
            Log::warning('Insights agent failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return $this->fail($thread, 'The insights model is unavailable.', ['error' => $e->getMessage()], 502);
        }

        $rawAnswer = trim((string) ($response['answer'] ?? ''));
        if ($rawAnswer === '') {
            $rawAnswer = trim((string) $response);
        }

        $explanation = trim((string) ($response['explanation'] ?? ''));
        $confidence = (string) ($response['confidence'] ?? 'medium');
        $toolResults = $response->toolResults ?? collect();

        $composed = AnswerComposer::compose($question, $toolResults, $rawAnswer);
        $answer = $composed['answer'];
        $composedRows = $composed['rows'];
        $groundedFromTools = $composed['composed_from_tools'];

        if ($groundedFromTools) {
            $confidence = $composed['confidence'];
            if ($explanation === '' || AnswerComposer::looksLikeRawJson($explanation)) {
                $explanation = 'Answer composed from warehouse tool results.';
            }
        }

        if ($this->shouldSalvageWithDirect($question, $composed)) {
            $salvaged = $this->directResolver->try($question);
            if ($salvaged !== null) {
                return $this->persistAnswer($thread, $question, $salvaged, rawAnswer: $rawAnswer);
            }
        }

        if ($answer === '') {
            return $this->fail($thread, 'The insights model returned an empty answer.', [
                'explanation' => $explanation,
            ], 422);
        }

        $tools = $this->summarizeTools($toolResults);
        $sqlPayload = $this->extractSqlPayload($toolResults);

        $rows = $composedRows !== [] ? $composedRows : ($sqlPayload['rows'] ?? []);
        $sql = $sqlPayload['sql'] ?? null;
        $rowSource = $sql !== null ? 'sql' : ($composedRows !== [] ? 'tools' : null);

        return $this->persistAnswer($thread, $question, [
            'answer' => $answer,
            'explanation' => $explanation,
            'confidence' => $confidence,
            'rows' => $rows,
            'tools' => $tools,
            'composed_from_tools' => $groundedFromTools,
            'warnings' => $composed['warnings'],
            'sql' => $sql,
            'row_source' => $rowSource,
        ], rawAnswer: $rawAnswer);
    }

    /**
     * @param  array{
     *     answer: string,
     *     explanation: string,
     *     confidence: string,
     *     rows: list<array<string, mixed>>,
     *     tools: list<array{name: string, arguments?: array<string, mixed>, result_preview?: string, calls?: int}>,
     *     composed_from_tools: bool,
     *     warnings: list<string>,
     *     sql: ?string,
     *     row_source: ?string
     * }  $payload
     */
    private function persistAnswer(ChatThread $thread, string $question, array $payload, ?string $rawAnswer): JsonResponse
    {
        $answer = $payload['answer'];
        $explanation = $payload['explanation'];
        $confidence = $payload['confidence'];
        $rows = $payload['rows'];
        $tools = $payload['tools'];
        $sql = $payload['sql'] ?? null;
        $rowSource = $payload['row_source'] ?? null;

        Log::info('Insights answer', [
            'question' => $question,
            'tools' => array_column($tools, 'name'),
            'confidence' => $confidence,
            'sql' => $sql,
            'composed_from_tools' => $payload['composed_from_tools'],
            'warnings' => $payload['warnings'],
            'raw_answer' => $rawAnswer !== null ? Str::limit($rawAnswer, 80) : null,
            'direct' => $rawAnswer === null,
        ]);

        $thread->messages()->create([
            'role' => 'assistant',
            'content' => $answer,
            'sql' => $sql,
            'payload' => [
                'explanation' => $explanation,
                'confidence' => $confidence,
                'tools' => $tools,
                'composed_from_tools' => $payload['composed_from_tools'],
                'row_count' => count($rows),
                'row_source' => $rowSource,
                'rows' => array_slice($rows, 0, self::SNAPSHOT_ROWS),
                'warnings' => $payload['warnings'],
            ],
        ]);

        $thread->touch();

        return response()->json([
            'thread_id' => $thread->id,
            'question' => $question,
            'answer' => $answer,
            'explanation' => $explanation,
            'confidence' => $confidence,
            'tools' => $tools,
            'sql' => $sql,
            'row_count' => count($rows),
            'row_source' => $rowSource,
            'rows' => $rows,
        ]);
    }

    /**
     * @param  array{answer: string, rows: list<array<string, mixed>>, warnings: list<string>}  $composed
     */
    private function shouldSalvageWithDirect(string $question, array $composed): bool
    {
        $intent = QuestionIntent::fromQuestion($question);

        if ($intent->wantsLargestArea) {
            return ! str_contains(strtolower($composed['answer']), 'km');
        }

        if ($intent->wantsContact && $intent->authoritySearch !== null) {
            foreach ($composed['rows'] as $row) {
                if ($intent->matchesAuthorityName((string) ($row['name'] ?? ''))) {
                    return false;
                }
            }

            return $composed['rows'] !== [];
        }

        return false;
    }

    /**
     * Resolve an existing owned thread or create a new one titled from the question.
     */
    private function resolveThread(Request $request, ?int $threadId, string $question): ChatThread
    {
        if ($threadId !== null) {
            $thread = ChatThread::query()
                ->where('id', $threadId)
                ->where('user_id', $request->user()->id)
                ->first();

            if ($thread !== null) {
                return $thread;
            }
        }

        return ChatThread::create([
            'user_id' => $request->user()->id,
            'title' => Str::limit($question, 60),
        ]);
    }

    /**
     * Persist an assistant error message and return the error response (with thread id).
     *
     * @param  array<string, mixed>  $extra
     */
    private function fail(ChatThread $thread, string $message, array $extra, int $status): JsonResponse
    {
        $thread->messages()->create([
            'role' => 'assistant',
            'content' => $message,
            'sql' => $extra['sql'] ?? ($extra['generated_sql'] ?? null),
            'payload' => ['error' => true] + $extra,
        ]);

        $thread->touch();

        return response()->json(['thread_id' => $thread->id, 'message' => $message] + $extra, $status);
    }

    private function authorizeThread(Request $request, ChatThread $thread): void
    {
        abort_if($thread->user_id !== $request->user()->id, 404);
    }

    /**
     * @param  Collection<int, ToolResult>|iterable<int, ToolResult>  $toolResults
     * @return list<array{name: string, arguments: array<string, mixed>, result_preview: string, calls: int}>
     */
    private function summarizeTools(iterable $toolResults): array
    {
        $byName = [];

        foreach ($toolResults as $result) {
            if (! $result instanceof ToolResult) {
                continue;
            }

            $name = (string) ($result->name ?? 'tool');
            $preview = is_string($result->result ?? null)
                ? Str::limit($result->result, 240)
                : '';
            $arguments = is_array($result->arguments ?? null)
                ? $result->arguments
                : (array) ($result->arguments ?? []);

            $byName[$name] = [
                'name' => $name,
                'arguments' => $arguments,
                'result_preview' => $preview,
                'calls' => ($byName[$name]['calls'] ?? 0) + 1,
            ];
        }

        return array_values($byName);
    }

    /**
     * Pull SQL + rows from the last successful run_warehouse_sql tool result (if any).
     *
     * @param  Collection<int, ToolResult>|iterable<int, ToolResult>  $toolResults
     * @return array{sql: ?string, rows: list<array<string, mixed>>}
     */
    private function extractSqlPayload(iterable $toolResults): array
    {
        $sql = null;
        $rows = [];

        foreach ($toolResults as $result) {
            if (! $result instanceof ToolResult) {
                continue;
            }

            if (($result->name ?? '') !== 'run_warehouse_sql') {
                continue;
            }

            $decoded = json_decode((string) ($result->result ?? ''), true);
            if (! is_array($decoded) || isset($decoded['error'])) {
                continue;
            }

            if (is_string($decoded['sql'] ?? null)) {
                $sql = $decoded['sql'];
            }
            if (is_array($decoded['rows'] ?? null)) {
                $rows = $decoded['rows'];
            }
        }

        return ['sql' => $sql, 'rows' => $rows];
    }
}
