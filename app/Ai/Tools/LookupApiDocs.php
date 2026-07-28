<?php

namespace App\Ai\Tools;

use App\Support\Insights\OpenApiCatalog;
use App\Support\Insights\ToolJson;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Retrieve relevant OpenAPI path docs to ground tool choice.
 */
class LookupApiDocs implements Tool
{
    use ReadsToolArgs;

    public function name(): string
    {
        return 'lookup_api_docs';
    }

    public function description(): Stringable|string
    {
        return 'Search the IMBY OpenAPI documentation for warehouse endpoints, parameters, and intent guidance. '
            .'Call this when unsure which tool/filters to use.';
    }

    public function handle(Request $request): Stringable|string
    {
        $query = $this->argString($request, 'query');
        if ($query === '') {
            return ToolJson::encode(['error' => 'query is required.']);
        }

        return ToolJson::encode([
            'query' => $query,
            'matches' => OpenApiCatalog::search($query, max(1, min(12, $this->argInt($request, 'limit', 8) ?? 8))),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->description('Keywords from the user question, e.g. "forecast NSW" or "authority phone".'),
            'limit' => $schema->integer()->description('Max matches (default 8).'),
        ];
    }
}
