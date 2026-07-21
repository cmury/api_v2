<?php

namespace App\Http\Controllers\Api;

use App\Ai\Agents\InsightsAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Insights\AskInsightRequest;
use App\Support\SqlGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class InsightsController extends Controller
{
    /**
     * The read-only connection AI-generated queries are executed against.
     */
    private const CONNECTION = 'data_readonly';

    /**
     * Answer a natural-language question about the planning data by having a
     * local LLM (via the Laravel AI SDK) generate a read-only SQL query, which
     * is then guarded and executed against the warehouse.
     */
    public function ask(AskInsightRequest $request): JsonResponse
    {
        $question = (string) $request->validated()['question'];

        try {
            $response = (new InsightsAgent)->prompt($question, timeout: 180);
        } catch (Throwable $e) {
            Log::warning('Insights agent failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'The insights model is unavailable.'], 502);
        }

        $generatedSql = (string) ($response['sql'] ?? '');
        $explanation = (string) ($response['explanation'] ?? '');

        try {
            $sql = SqlGuard::sanitize($generatedSql);
        } catch (InvalidArgumentException $e) {
            Log::info('Insights query rejected', ['sql' => $generatedSql, 'reason' => $e->getMessage()]);

            return response()->json([
                'message' => 'Could not build a safe query for that question.',
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
            return response()->json([
                'message' => 'The generated query could not be executed.',
                'reason' => $e->getMessage(),
                'sql' => $sql,
            ], 422);
        }

        return response()->json([
            'question' => $question,
            'explanation' => $explanation,
            'sql' => $sql,
            'row_count' => count($rows),
            'rows' => $rows,
        ]);
    }
}
