<?php

namespace App\Ai\Tools;

use App\Support\Insights\ToolJson;
use App\Support\SqlGuard;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Last-resort read-only SQL against the warehouse (when REST tools cannot express the question).
 */
class RunWarehouseSql implements Tool
{
    use ReadsToolArgs;

    private const CONNECTION = 'data_readonly';

    public function name(): string
    {
        return 'run_warehouse_sql';
    }

    public function description(): Stringable|string
    {
        return 'Execute ONE read-only PostgreSQL SELECT as a last resort when other tools cannot answer '
            .'(complex joins, PostGIS rankings, novel aggregations). Prefer search_*/get_stats/list_taxonomies first. '
            .'Allowed tables: authorities, applications, locations, application_locations, legislation, '
            .'application_legislation, application_classes/types (+ pivots), development_classes/types (+ pivots), '
            .'decision_classes/types (+ pivots), facilities. Always include LIMIT ≤ 200. Never mutate data.';
    }

    public function handle(Request $request): Stringable|string
    {
        $sql = $this->argString($request, 'sql');
        $question = $this->argString($request, 'question');

        if ($sql === '') {
            return ToolJson::encode(['error' => 'sql is required.']);
        }

        try {
            $clean = SqlGuard::sanitize($sql, question: $question !== '' ? $question : null);
        } catch (InvalidArgumentException $e) {
            return ToolJson::encode([
                'error' => 'SQL rejected: '.$e->getMessage(),
                'generated_sql' => $sql,
            ]);
        }

        try {
            $connection = DB::connection(self::CONNECTION);
            $connection->statement("SET statement_timeout = '10s'");
            $rows = $connection->select($clean);
        } catch (Throwable $e) {
            return ToolJson::encode([
                'error' => 'Query failed: '.$e->getMessage(),
                'sql' => $clean,
            ]);
        }

        return ToolJson::encode([
            'sql' => $clean,
            'row_count' => count($rows),
            'rows' => ToolJson::rows($rows, 40),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'sql' => $schema->string()->required()->description('A single SELECT (or WITH … SELECT). Must include LIMIT ≤ 200.'),
            'question' => $schema->string()->description('Original user question (helps date-intent repairs).'),
        ];
    }
}
