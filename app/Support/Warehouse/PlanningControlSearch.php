<?php

namespace App\Support\Warehouse;

use App\Models\PlanningControl;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Shared planning_controls list / point-in-polygon logic for REST + Insights.
 */
final class PlanningControlSearch
{
    private const ORDER_COLUMNS = [
        'layer', 'code', 'label', 'lga_name', 'epi_name', 'created_at',
    ];

    /**
     * Known layer keys used by NSW Principal Planning (and future hazard feeds).
     *
     * @var list<string>
     */
    public const LAYERS = [
        'zoning',
        'zoning_additional',
        'fsr',
        'fsr_additional',
        'height',
        'height_additional',
        'lot_size',
        'lot_size_additional',
        'heritage_epi',
        'heritage_state',
        'lep',
        'land_reclassification',
        'land_reservation',
        'dwelling_density',
        'foreshore',
        'bushfire',
        'flood',
        'landslide',
    ];

    public function query(
        ?string $search = null,
        ?string $layer = null,
        ?string $code = null,
        ?string $epiType = null,
        ?string $lgaName = null,
        ?int $authorityId = null,
        ?string $source = null,
        ?array $bounds = null,
    ): Builder {
        $query = PlanningControl::query()
            ->select('planning_controls.*')
            ->whereNotNull('planning_controls.geom');

        if ($layer !== null && $layer !== '') {
            $query->where('planning_controls.layer', strtolower($layer));
        }

        if ($code !== null && $code !== '') {
            $query->where('planning_controls.code', 'ilike', $code);
        }

        if ($epiType !== null && $epiType !== '') {
            $query->where('planning_controls.epi_type', strtoupper($epiType));
        }

        if ($lgaName !== null && $lgaName !== '') {
            $query->where('planning_controls.lga_name', 'ilike', '%'.$lgaName.'%');
        }

        if ($authorityId !== null && $authorityId > 0) {
            $query->where('planning_controls.authority_id', $authorityId);
        }

        if ($source !== null && $source !== '') {
            $query->where('planning_controls.source', $source);
        }

        if ($search !== null && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('planning_controls.code', 'ilike', $like)
                    ->orWhere('planning_controls.label', 'ilike', $like)
                    ->orWhere('planning_controls.purpose', 'ilike', $like)
                    ->orWhere('planning_controls.epi_name', 'ilike', $like)
                    ->orWhere('planning_controls.lga_name', 'ilike', $like);
            });
        }

        if ($bounds !== null && count($bounds) === 4) {
            [$latMax, $lngMax, $latMin, $lngMin] = $bounds;
            $query->whereRaw(
                'planning_controls.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography',
                [(float) $lngMin, (float) $latMin, (float) $lngMax, (float) $latMax],
            );
        }

        return $query;
    }

    public function ordered(Builder $query, string $order = 'layer'): Builder
    {
        [$column, $direction] = ListOrdering::parse($order, 'layer');
        $column = ListOrdering::column($column, self::ORDER_COLUMNS, 'layer');

        return $query
            ->orderBy("planning_controls.{$column}", $direction)
            ->orderBy('planning_controls.code')
            ->orderBy('planning_controls.id');
    }

    /**
     * Controls whose polygon contains the given WGS84 point.
     *
     * @param  list<string>|null  $layers
     *
     * @throws InvalidArgumentException
     */
    public function atPoint(
        float $lat,
        float $lng,
        ?array $layers = null,
        ?string $code = null,
        int $limit = 50,
    ): Builder {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new InvalidArgumentException('lat/lng out of range.');
        }

        $query = PlanningControl::query()
            ->select('planning_controls.*')
            ->whereNotNull('planning_controls.geom')
            ->whereRaw(
                'ST_Intersects(planning_controls.geom, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)',
                [$lng, $lat],
            );

        if ($layers !== null && $layers !== []) {
            $query->whereIn(
                'planning_controls.layer',
                array_map(static fn (string $layer): string => strtolower($layer), $layers),
            );
        }

        if ($code !== null && $code !== '') {
            $query->where('planning_controls.code', 'ilike', $code);
        }

        return $query
            ->orderBy('planning_controls.layer')
            ->orderBy('planning_controls.code')
            ->limit(max(1, min(200, $limit)));
    }

    /**
     * Attach GeoJSON geometry onto query results (ST_AsGeoJSON).
     */
    public function withGeometry(Builder $query): Builder
    {
        return $query->selectRaw('ST_AsGeoJSON(planning_controls.geom::geometry) AS geometry_geojson');
    }

    /**
     * Distinct layer keys present in the warehouse.
     *
     * @return list<array{layer: string, count: int}>
     */
    public function layers(): array
    {
        return PlanningControl::query()
            ->select('layer')
            ->selectRaw('COUNT(*) AS count')
            ->groupBy('layer')
            ->orderBy('layer')
            ->get()
            ->map(fn ($row) => [
                'layer' => (string) $row->layer,
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * Distinct codes for a layer (optional LGA filter).
     *
     * @return list<array{code: string, label: ?string, count: int}>
     */
    public function codes(?string $layer = null, ?string $lgaName = null, int $limit = 200): array
    {
        $query = PlanningControl::query()
            ->select('code')
            ->selectRaw('MIN(label) AS label')
            ->selectRaw('COUNT(*) AS count')
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->groupBy('code')
            ->orderBy('code')
            ->limit(max(1, min(500, $limit)));

        if ($layer !== null && $layer !== '') {
            $query->where('layer', strtolower($layer));
        }

        if ($lgaName !== null && $lgaName !== '') {
            $query->where('lga_name', 'ilike', '%'.$lgaName.'%');
        }

        return $query->get()->map(fn ($row) => [
            'code' => (string) $row->code,
            'label' => $row->label !== null ? (string) $row->label : null,
            'count' => (int) $row->count,
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(PlanningControl $control, bool $includeGeometry = false): array
    {
        $row = [
            'id' => $control->id,
            'source' => $control->source,
            'source_id' => $control->source_id,
            'layer' => $control->layer,
            'code' => $control->code,
            'label' => $control->label,
            'purpose' => $control->purpose,
            'epi_name' => $control->epi_name,
            'epi_type' => $control->epi_type,
            'lga_name' => $control->lga_name,
            'authority_id' => $control->authority_id,
            'source_modified_at' => $control->source_modified_at,
        ];

        if ($includeGeometry && ! empty($control->geometry_geojson)) {
            $geometry = json_decode((string) $control->geometry_geojson, true);
            if (is_array($geometry)) {
                $row['geometry'] = $geometry;
            }
        }

        return $row;
    }
}
