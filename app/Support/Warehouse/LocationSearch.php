<?php

namespace App\Support\Warehouse;

use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared location list query used by GET /api/locations and search_locations tool.
 */
final class LocationSearch
{
    private const ORDER_COLUMNS = [
        'formatted_address', 'street', 'suburb', 'state', 'post_code', 'created_at', 'applications_count',
    ];

    public function query(
        ?string $search = null,
        ?string $state = null,
        ?string $suburb = null,
        ?int $authorityId = null,
    ): Builder {
        $query = Location::query()
            ->withCount('applications')
            ->select('locations.*')
            ->selectRaw('ST_Y(locations.geom::geometry) AS lat')
            ->selectRaw('ST_X(locations.geom::geometry) AS lng');

        if ($state !== null && $state !== '') {
            $query->where('locations.state', strtoupper($state));
        }

        if ($suburb !== null && $suburb !== '') {
            $query->where('locations.suburb', 'ilike', $suburb);
        }

        if ($authorityId !== null) {
            $query->join('authority_locations as al', 'al.location_id', '=', 'locations.id')
                ->where('al.authority_id', $authorityId);
        }

        if ($search !== null && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('locations.formatted_address', 'ilike', $like)
                    ->orWhere('locations.street', 'ilike', $like)
                    ->orWhere('locations.suburb', 'ilike', $like)
                    ->orWhere('locations.post_code', 'ilike', $like)
                    ->orWhere('locations.state', 'ilike', $like);
            });
        }

        return $query;
    }

    public function ordered(Builder $query, string $order = 'suburb'): Builder
    {
        [$column, $direction] = ListOrdering::parse($order, 'suburb');
        $column = ListOrdering::column($column, self::ORDER_COLUMNS);

        $orderColumn = $column === 'applications_count' ? $column : "locations.{$column}";
        $query->orderBy($orderColumn, $direction);

        if ($column !== 'street') {
            $query->orderBy('locations.street');
        }

        return $query;
    }
}
