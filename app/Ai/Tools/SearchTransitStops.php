<?php

namespace App\Ai\Tools;

use App\Support\Insights\ToolJson;
use App\Support\Warehouse\TransitStopSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mirrors GET /api/transit/stops — NSW transport facility points.
 */
class SearchTransitStops implements Tool
{
    use ReadsToolArgs;

    public function __construct(
        private readonly TransitStopSearch $transitStopSearch = new TransitStopSearch,
    ) {}

    public function name(): string
    {
        return 'search_transit_stops';
    }

    public function description(): Stringable|string
    {
        return 'Search NSW transport facilities (train stations, bus stations, airports, etc.) from the '
            .'authoritative transit_stops table. Use to resolve station names like "Chatswood Railway Station" '
            .'or "Sydney Central" before searching applications nearby.';
    }

    public function handle(Request $request): Stringable|string
    {
        $perPage = max(1, min(50, $this->argInt($request, 'per_page', 10) ?? 10));
        $search = $this->argString($request, 'search');
        if ($search === '') {
            $search = $this->argString($request, 'name');
        }

        $query = $this->transitStopSearch->query(
            $search !== '' ? $search : null,
            $this->hasArg($request, 'state') ? $this->argString($request, 'state') : null,
            $this->hasArg($request, 'stop_type') ? $this->argString($request, 'stop_type') : null,
            $this->hasArg($request, 'operational_status') ? $this->argString($request, 'operational_status') : null,
        );

        $rows = $this->transitStopSearch->ordered($query)
            ->limit($perPage)
            ->get();

        return ToolJson::encode([
            'count' => $rows->count(),
            'transit_stops' => $rows->map(fn ($stop) => $this->transitStopSearch->toArray($stop))->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Station / facility name fragment, e.g. Chatswood or Central.'),
            'name' => $schema->string()->description('Alias of search.'),
            'state' => $schema->string()->description('State code; usually NSW.'),
            'stop_type' => $schema->string()->description('train | bus | airport | ferry | marina | helipad | parking | …'),
            'operational_status' => $schema->string()->description('operational | proposed | disused | …'),
            'per_page' => $schema->integer()->description('Max rows (1–50, default 10).'),
        ];
    }
}
