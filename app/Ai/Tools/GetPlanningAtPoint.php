<?php

namespace App\Ai\Tools;

use App\Support\Insights\ToolJson;
use App\Support\Warehouse\PlanningControlSearch;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mirrors GET /api/planning-controls/at-point — point-in-polygon zoning lookup.
 */
class GetPlanningAtPoint implements Tool
{
    use ReadsToolArgs;

    public function __construct(
        private readonly PlanningControlSearch $planningControlSearch = new PlanningControlSearch,
    ) {}

    public function name(): string
    {
        return 'get_planning_at_point';
    }

    public function description(): Stringable|string
    {
        return 'Return planning controls that contain a WGS84 point (what zone / FSR / height applies here). '
            .'Pass lat + lng. Optionally restrict layers (default: all loaded layers; start with zoning). '
            .'Example: zoning at Chatswood station coordinates.';
    }

    public function handle(Request $request): Stringable|string
    {
        $lat = $this->argFloat($request, 'lat');
        $lng = $this->argFloat($request, 'lng');
        if ($lat === null || $lng === null) {
            return ToolJson::encode(['error' => 'lat and lng are required.']);
        }

        $layers = null;
        if ($this->hasArg($request, 'layers')) {
            $raw = $request['layers'] ?? null;
            if (is_string($raw) && $raw !== '') {
                $layers = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $raw) ?: [])));
            } elseif (is_array($raw)) {
                $layers = array_values(array_filter(array_map('strval', $raw)));
            }
        } elseif ($this->hasArg($request, 'layer')) {
            $layers = [$this->argString($request, 'layer')];
        }

        try {
            $rows = $this->planningControlSearch->atPoint(
                $lat,
                $lng,
                $layers,
                $this->hasArg($request, 'code') ? $this->argString($request, 'code') : null,
                max(1, min(50, $this->argInt($request, 'limit', 20) ?? 20)),
            )->get();
        } catch (InvalidArgumentException $e) {
            return ToolJson::encode(['error' => $e->getMessage()]);
        }

        return ToolJson::encode([
            'lat' => $lat,
            'lng' => $lng,
            'count' => $rows->count(),
            'planning_controls' => $rows->map(
                fn ($control) => $this->planningControlSearch->toArray($control)
            )->all(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lat' => $schema->number()->description('WGS84 latitude.')->required(),
            'lng' => $schema->number()->description('WGS84 longitude.')->required(),
            'layers' => $schema->string()->description('Comma-separated layers, e.g. zoning,fsr,height.'),
            'layer' => $schema->string()->description('Single layer alias of layers.'),
            'code' => $schema->string()->description('Optional code filter, e.g. R2.'),
            'limit' => $schema->integer()->description('Max rows (1–50, default 20).'),
        ];
    }

    private function argFloat(Request $request, string $key): ?float
    {
        $value = $request[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
