<?php

namespace App\Ai\Tools;

use App\Support\Insights\ToolJson;
use App\Support\Warehouse\FacilitySearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mirrors GET /api/facilities — transport, education, and other point facilities.
 */
class SearchFacilities implements Tool
{
    use ReadsToolArgs;

    public function __construct(
        private readonly FacilitySearch $facilitySearch = new FacilitySearch,
    ) {}

    public function name(): string
    {
        return 'search_facilities';
    }

    public function description(): Stringable|string
    {
        return 'Search point facilities (train/bus/ferry stations, airports, schools, universities, …) '
            .'from the facilities table. Use to resolve names like "Chatswood Railway Station" or '
            .'"Sydney Boys High" before searching applications nearby.';
    }

    public function handle(Request $request): Stringable|string
    {
        $perPage = max(1, min(50, $this->argInt($request, 'per_page', 10) ?? 10));
        $search = $this->argString($request, 'search');
        if ($search === '') {
            $search = $this->argString($request, 'name');
        }

        $query = $this->facilitySearch->query(
            $search !== '' ? $search : null,
            $this->hasArg($request, 'state') ? $this->argString($request, 'state') : null,
            $this->hasArg($request, 'facility_type') ? $this->argString($request, 'facility_type') : null,
            $this->hasArg($request, 'operational_status') ? $this->argString($request, 'operational_status') : null,
        );

        $rows = $this->facilitySearch->ordered($query)
            ->limit($perPage)
            ->get();

        return ToolJson::encode([
            'count' => $rows->count(),
            'facilities' => $rows->map(fn ($facility) => $this->facilitySearch->toArray($facility))->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Facility name fragment, e.g. Chatswood or Central.'),
            'name' => $schema->string()->description('Alias of search.'),
            'state' => $schema->string()->description('State code; usually NSW or ACT.'),
            'facility_type' => $schema->string()->description('train | bus | airport | ferry | primary_school | high_school | university | …'),
            'operational_status' => $schema->string()->description('operational | proposed | disused | …'),
            'per_page' => $schema->integer()->description('Max rows (1–50, default 10).'),
        ];
    }
}
