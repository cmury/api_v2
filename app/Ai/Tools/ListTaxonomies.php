<?php

namespace App\Ai\Tools;

use App\Support\Insights\ToolJson;
use App\Support\Warehouse\TaxonomyCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mirrors GET /api/taxonomies/*.
 */
class ListTaxonomies implements Tool
{
    use ReadsToolArgs;

    public function __construct(
        private readonly TaxonomyCatalog $taxonomyCatalog = new TaxonomyCatalog,
    ) {}

    public function name(): string
    {
        return 'list_taxonomies';
    }

    public function description(): Stringable|string
    {
        return 'List filter vocabulary: application/development/decision classes and types. '
            .'Use to resolve class ids before search_applications / get_stats, or to explain BCA Class 2 etc.';
    }

    public function handle(Request $request): Stringable|string
    {
        $kind = strtolower($this->argString($request, 'kind'));
        $jurisdiction = $this->hasArg($request, 'jurisdiction')
            ? strtoupper($this->argString($request, 'jurisdiction'))
            : null;
        $classId = $this->argInt($request, 'class_id');
        $search = $this->argString($request, 'search');

        try {
            $rows = $this->taxonomyCatalog->list($kind, $jurisdiction, $classId, $search !== '' ? $search : null);
        } catch (InvalidArgumentException $e) {
            return ToolJson::encode(['error' => $e->getMessage()]);
        }

        return ToolJson::encode([
            'kind' => $kind,
            'count' => count($rows),
            'items' => $rows,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->required()->description(
                'application_classes | application_types | development_classes | development_types | decision_classes | decision_types'
            ),
            'jurisdiction' => $schema->string()->description('NSW, ACT, …'),
            'class_id' => $schema->integer()->description('Parent class id when listing types.'),
            'search' => $schema->string()->description('Optional name filter.'),
        ];
    }
}
