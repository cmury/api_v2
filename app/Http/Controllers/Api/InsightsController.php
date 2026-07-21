<?php

namespace App\Http\Controllers\Api;

use App\Ai\Agents\InsightsAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Insights\AskInsightRequest;
use App\Http\Resources\ChatThreadResource;
use App\Models\ChatThread;
use App\Support\SqlGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class InsightsController extends Controller
{
    /**
     * The read-only connection AI-generated queries are executed against.
     */
    private const CONNECTION = 'data_readonly';

    /**
     * Max result rows persisted in a message payload (full set is still returned).
     */
    private const SNAPSHOT_ROWS = 50;

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
     * Answer a natural-language question and persist the exchange to a thread.
     */
    public function ask(AskInsightRequest $request): JsonResponse
    {
        $data = $request->validated();
        $question = (string) $data['question'];

        $thread = $this->resolveThread($request, $data['thread_id'] ?? null, $question);

        $thread->messages()->create([
            'role' => 'user',
            'content' => $question,
        ]);

        try {
            $response = (new InsightsAgent)->prompt($question, timeout: 180);
        } catch (Throwable $e) {
            Log::warning('Insights agent failed', ['error' => $e->getMessage()]);

            return $this->fail($thread, 'The insights model is unavailable.', ['error' => $e->getMessage()], 502);
        }

        $generatedSql = (string) ($response['sql'] ?? '');
        $explanation = (string) ($response['explanation'] ?? '');

        try {
            $sql = SqlGuard::sanitize($generatedSql);
        } catch (InvalidArgumentException $e) {
            Log::info('Insights query rejected', ['sql' => $generatedSql, 'reason' => $e->getMessage()]);

            return $this->fail($thread, 'Could not build a safe query for that question.', [
                'reason' => $e->getMessage(),
                'generated_sql' => $generatedSql,
            ], 422);
        }

        Log::info('Insights query', ['question' => $question, 'sql' => $sql]);

        try {
            $connection = DB::connection(self::CONNECTION);
            $connection->statement("SET statement_timeout = '10s'");
            $rows = $connection->select($sql);
        } catch (Throwable $e) {
            return $this->fail($thread, 'The generated query could not be executed.', [
                'reason' => $e->getMessage(),
                'sql' => $sql,
            ], 422);
        }

        $thread->messages()->create([
            'role' => 'assistant',
            'content' => $explanation,
            'sql' => $sql,
            'payload' => [
                'row_count' => count($rows),
                'rows' => array_slice($rows, 0, self::SNAPSHOT_ROWS),
            ],
        ]);

        $thread->touch();

        return response()->json([
            'thread_id' => $thread->id,
            'question' => $question,
            'explanation' => $explanation,
            'sql' => $sql,
            'row_count' => count($rows),
            'rows' => $rows,
        ]);
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
}
