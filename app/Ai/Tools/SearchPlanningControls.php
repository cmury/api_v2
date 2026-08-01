<?php

namespace App\Ai\Tools;

use App\Support\Insights\ToolJson;
use App\Support\Warehouse\PlanningControlSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mirrors GET /api/planning-controls — EPI zoning / FSR / height polygons.
 */
class SearchPlanningControls implements Tool
{
    use ReadsToolArgs;

    public function __construct(
        private readonly PlanningControlSearch $planningControlSearch = new PlanningControlSearch,
    ) {}

    public function name(): string
    {
        return 'search_planning_controls';
    }

    public function description(): Stringable|string
    {
        return 'Search NSW principal planning control polygons (zoning, FSR, height, lot size, heritage, …). '
            .'Filter by layer (e.g. zoning), zone code (R2, B4), LGA name, or LEP/SEPP. '
            .'Prefer get_planning_at_point when the user asks what zone applies at a lat/lng.';
    }

    public function handle(Request $request): Stringable|string
    {
        $perPage = max(1, min(50, $this->argInt($request, 'per_page', 10) ?? 10));
        $search = $this->argString($request, 'search');

        $query = $this->planningControlSearch->query(
            $search !== '' ? $search : null,
            $this->hasArg($request, 'layer') ? $this->argString($request, 'layer') : null,
            $this->hasArg($request, 'code') ? $this->argString($request, 'code') : null,
            $this->hasArg($request, 'epi_type') ? $this->argString($request, 'epi_type') : null,
            $this->hasArg($request, 'lga_name') ? $this->argString($request, 'lga_name') : null,
            $this->argInt($request, 'authority_id'),
            $this->hasArg($request, 'source') ? $this->argString($request, 'source') : null,
        );

        $rows = $this->planningControlSearch->ordered($query)
            ->limit($perPage)
            ->get();

        return ToolJson::encode([
            'count' => $rows->count(),
            'planning_controls' => $rows->map(
                fn ($control) => $this->planningControlSearch->toArray($control)
            )->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Free-text across code, label, purpose, EPI, LGA.'),
            'layer' => $schema->string()->description('zoning | fsr | height | lot_size | heritage_epi | …'),
            'code' => $schema->string()->description('Zone / control code, e.g. R2, B4, SP2.'),
            'epi_type' => $schema->string()->description('LEP or SEPP.'),
            'lga_name' => $schema->string()->description('LGA name fragment from the planning source.'),
            'authority_id' => $schema->integer()->description('Optional matched authority id.'),
            'source' => $schema->string()->description('e.g. nsw-principal-planning.'),
            'per_page' => $schema->integer()->description('Max rows (1–50, default 10).'),
        ];
    }
}
