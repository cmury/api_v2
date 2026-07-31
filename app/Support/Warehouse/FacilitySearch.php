<?php

namespace App\Support\Warehouse;

use App\Models\Facility;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Shared facility list / resolve logic for REST + Insights tools.
 */
final class FacilitySearch
{
    private const ORDER_COLUMNS = [
        'name', 'facility_type', 'state', 'operational_status', 'created_at',
    ];

    public function query(
        ?string $search = null,
        ?string $state = null,
        ?string $facilityType = null,
        ?string $operationalStatus = null,
    ): Builder {
        $query = Facility::query()
            ->select('facilities.*')
            ->selectRaw('ST_Y(facilities.geom::geometry) AS lat')
            ->selectRaw('ST_X(facilities.geom::geometry) AS lng')
            ->whereNotNull('facilities.geom');

        if ($state !== null && $state !== '') {
            $query->where('facilities.state', strtoupper($state));
        }

        if ($facilityType !== null && $facilityType !== '') {
            $query->where('facilities.facility_type', strtolower($facilityType));
        }

        if ($operationalStatus !== null && $operationalStatus !== '') {
            $query->where('facilities.operational_status', strtolower($operationalStatus));
        }

        if ($search !== null && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('facilities.name', 'ilike', $like)
                    ->orWhere('facilities.name_alt', 'ilike', $like);
            });
        }

        return $query;
    }

    public function ordered(Builder $query, string $order = 'name'): Builder
    {
        [$column, $direction] = ListOrdering::parse($order, 'name');
        $column = ListOrdering::column($column, self::ORDER_COLUMNS);

        return $query->orderBy("facilities.{$column}", $direction)->orderBy('facilities.id');
    }

    /**
     * Resolve one authoritative facility for near-radius queries.
     *
     * Prefers exact / prefix name matches, then operational train stops when relevant.
     *
     * @throws InvalidArgumentException
     */
    public function resolve(
        ?int $facilityId = null,
        ?string $search = null,
        ?string $state = null,
        ?string $facilityType = null,
    ): Facility {
        if ($facilityId !== null && $facilityId > 0) {
            $facility = $this->query(state: $state, facilityType: $facilityType)
                ->whereKey($facilityId)
                ->first();

            if ($facility === null) {
                throw new InvalidArgumentException("Facility {$facilityId} not found.");
            }

            return $facility;
        }

        $search = $search !== null ? trim($search) : '';
        if ($search === '') {
            throw new InvalidArgumentException('Provide facility_id or facility_search.');
        }

        $query = $this->query($search, $state, $facilityType);
        $needle = strtolower($search);

        $candidates = $query
            ->orderByRaw(
                'CASE
                    WHEN lower(facilities.name) = ? THEN 0
                    WHEN lower(facilities.name) LIKE ? THEN 1
                    WHEN lower(coalesce(facilities.name_alt, \'\')) = ? THEN 2
                    WHEN lower(coalesce(facilities.name_alt, \'\')) LIKE ? THEN 3
                    ELSE 4
                END',
                [$needle, $needle.'%', $needle, $needle.'%'],
            )
            ->orderByRaw(
                "CASE WHEN facilities.operational_status = 'operational' THEN 0 ELSE 1 END",
            )
            ->orderByRaw(
                "CASE WHEN facilities.facility_type = 'train' THEN 0 ELSE 1 END",
            )
            ->orderBy('facilities.name')
            ->limit(25)
            ->get();

        if ($candidates->isEmpty()) {
            throw new InvalidArgumentException('No facilities matched that search.');
        }

        return $candidates->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Facility $facility): array
    {
        return [
            'id' => $facility->id,
            'source' => $facility->source,
            'source_id' => $facility->source_id,
            'facility_type' => $facility->facility_type,
            'name' => $facility->name,
            'name_alt' => $facility->name_alt,
            'operational_status' => $facility->operational_status,
            'state' => $facility->state,
            'lat' => isset($facility->lat) ? (float) $facility->lat : null,
            'lng' => isset($facility->lng) ? (float) $facility->lng : null,
            'source_modified_at' => $facility->source_modified_at,
        ];
    }
}
