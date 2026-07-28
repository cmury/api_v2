<?php

namespace App\Ai\Tools;

use App\Models\Location;
use App\Support\Insights\ToolJson;
use App\Support\Warehouse\LocationSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mirrors GET /api/locations — development site addresses.
 */
class SearchLocations implements Tool
{
    use ReadsToolArgs;

    public function __construct(
        private readonly LocationSearch $locationSearch = new LocationSearch,
    ) {}

    public function name(): string
    {
        return 'search_locations';
    }

    public function description(): Stringable|string
    {
        return 'Search development site locations (suburb / address). Not for council postal addresses — '
            .'use search_authorities for those. Useful before filtering applications by location_id or suburb.';
    }

    public function handle(Request $request): Stringable|string
    {
        $perPage = max(1, min(50, $this->argInt($request, 'per_page', 10) ?? 10));
        $search = $this->argString($request, 'search');

        $query = $this->locationSearch->query(
            $search !== '' ? $search : null,
            $this->hasArg($request, 'state') ? $this->argString($request, 'state') : null,
            $this->hasArg($request, 'suburb') ? $this->argString($request, 'suburb') : null,
            $this->hasArg($request, 'authority_id') ? $this->argInt($request, 'authority_id') : null,
        );

        $rows = $this->locationSearch->ordered($query)
            ->limit($perPage)
            ->get([
                'id', 'suburb', 'state', 'post_code', 'street', 'formatted_address',
            ]);

        return ToolJson::encode([
            'count' => $rows->count(),
            'locations' => $rows->map(fn (Location $l) => [
                'id' => $l->id,
                'suburb' => $l->suburb,
                'state' => $l->state,
                'post_code' => $l->post_code,
                'street' => $l->street,
                'formatted_address' => $l->formatted_address,
                'applications_count' => $l->applications_count,
            ])->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string(),
            'suburb' => $schema->string(),
            'state' => $schema->string(),
            'authority_id' => $schema->integer(),
            'per_page' => $schema->integer()->description('Max rows (1–50, default 10).'),
        ];
    }
}
