<?php

namespace App\Support\Warehouse;

use App\Models\TransitStop;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Shared transit stop list / resolve logic for REST + Insights tools.
 */
final class TransitStopSearch
{
    private const ORDER_COLUMNS = [
        'name', 'stop_type', 'state', 'operational_status', 'created_at',
    ];

    public function query(
        ?string $search = null,
        ?string $state = null,
        ?string $stopType = null,
        ?string $operationalStatus = null,
    ): Builder {
        $query = TransitStop::query()
            ->select('transit_stops.*')
            ->selectRaw('ST_Y(transit_stops.geom::geometry) AS lat')
            ->selectRaw('ST_X(transit_stops.geom::geometry) AS lng')
            ->whereNotNull('transit_stops.geom');

        if ($state !== null && $state !== '') {
            $query->where('transit_stops.state', strtoupper($state));
        }

        if ($stopType !== null && $stopType !== '') {
            $query->where('transit_stops.stop_type', strtolower($stopType));
        }

        if ($operationalStatus !== null && $operationalStatus !== '') {
            $query->where('transit_stops.operational_status', strtolower($operationalStatus));
        }

        if ($search !== null && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('transit_stops.name', 'ilike', $like)
                    ->orWhere('transit_stops.name_alt', 'ilike', $like);
            });
        }

        return $query;
    }

    public function ordered(Builder $query, string $order = 'name'): Builder
    {
        [$column, $direction] = ListOrdering::parse($order, 'name');
        $column = ListOrdering::column($column, self::ORDER_COLUMNS);

        return $query->orderBy("transit_stops.{$column}", $direction)->orderBy('transit_stops.id');
    }

    /**
     * Resolve one authoritative stop for near-radius queries.
     *
     * Prefers exact / prefix name matches, then operational train stops when relevant.
     *
     * @throws InvalidArgumentException
     */
    public function resolve(
        ?int $transitStopId = null,
        ?string $search = null,
        ?string $state = null,
        ?string $stopType = null,
    ): TransitStop {
        if ($transitStopId !== null && $transitStopId > 0) {
            $stop = $this->query(state: $state, stopType: $stopType)
                ->whereKey($transitStopId)
                ->first();

            if ($stop === null) {
                throw new InvalidArgumentException("Transit stop {$transitStopId} not found.");
            }

            return $stop;
        }

        $search = $search !== null ? trim($search) : '';
        if ($search === '') {
            throw new InvalidArgumentException('Provide transit_stop_id or stop_search.');
        }

        $query = $this->query($search, $state, $stopType);
        $needle = strtolower($search);

        $candidates = $query
            ->orderByRaw(
                'CASE
                    WHEN lower(transit_stops.name) = ? THEN 0
                    WHEN lower(transit_stops.name) LIKE ? THEN 1
                    WHEN lower(coalesce(transit_stops.name_alt, \'\')) = ? THEN 2
                    WHEN lower(coalesce(transit_stops.name_alt, \'\')) LIKE ? THEN 3
                    ELSE 4
                END',
                [$needle, $needle.'%', $needle, $needle.'%'],
            )
            ->orderByRaw(
                "CASE WHEN transit_stops.operational_status = 'operational' THEN 0 ELSE 1 END",
            )
            ->orderByRaw(
                "CASE WHEN transit_stops.stop_type = 'train' THEN 0 ELSE 1 END",
            )
            ->orderBy('transit_stops.name')
            ->limit(25)
            ->get();

        if ($candidates->isEmpty()) {
            throw new InvalidArgumentException('No transit stops matched that search.');
        }

        return $candidates->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(TransitStop $stop): array
    {
        return [
            'id' => $stop->id,
            'source' => $stop->source,
            'source_id' => $stop->source_id,
            'stop_type' => $stop->stop_type,
            'name' => $stop->name,
            'name_alt' => $stop->name_alt,
            'operational_status' => $stop->operational_status,
            'state' => $stop->state,
            'lat' => isset($stop->lat) ? (float) $stop->lat : null,
            'lng' => isset($stop->lng) ? (float) $stop->lng : null,
            'source_modified_at' => $stop->source_modified_at,
        ];
    }
}
