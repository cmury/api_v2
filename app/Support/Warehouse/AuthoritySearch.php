<?php

namespace App\Support\Warehouse;

use App\Models\Authority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Shared authority list query used by GET /api/authorities and search_authorities tool.
 */
class AuthoritySearch
{
    private const ORDER_COLUMNS = ['name', 'state', 'region', 'created_at', 'applications_count'];

    public function query(
        ?string $search = null,
        ?string $state = null,
        bool $includeAmalgamated = false,
    ): Builder {
        $query = Authority::query()->withCount('applications');

        if (! $includeAmalgamated) {
            $query->current();
        }

        if ($state !== null && $state !== '') {
            $query->where('state', strtoupper($state));
        }

        if ($search !== null && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'ilike', $like)
                    ->orWhere('region', 'ilike', $like)
                    ->orWhere('state', 'ilike', $like)
                    ->orWhere('tracking_system', 'ilike', $like);
            });
        }

        return $query;
    }

    public function ordered(Builder $query, string $order = 'name'): Builder
    {
        [$column, $direction] = ListOrdering::parse($order);
        $column = ListOrdering::column($column, self::ORDER_COLUMNS);

        return $query->orderBy($column, $direction);
    }

    public function findBestMatch(string $search, ?string $state = null): ?Authority
    {
        $search = trim($search);
        if ($search === '') {
            return null;
        }

        $candidates = $this->ordered(
            $this->query($search, $state, false),
            'name',
        )->limit(15)->get();

        if ($candidates->isEmpty()) {
            return null;
        }

        $needle = strtolower($search);

        $best = $candidates->first(
            fn (Authority $authority) => str_starts_with(strtolower((string) $authority->name), $needle),
        );

        if ($best instanceof Authority) {
            return $best;
        }

        $best = $candidates->first(
            fn (Authority $authority) => str_contains(strtolower((string) $authority->name), $needle),
        );

        return $best instanceof Authority ? $best : $candidates->first();
    }

    /**
     * Rank current authorities by LGA boundary area (PostGIS only).
     *
     * @return Collection<int, Authority&object{area_km2: float|int|string|null}>
     */
    public function rankedByArea(?string $state = null, int $limit = 5): Collection
    {
        if (Authority::query()->getConnection()->getDriverName() !== 'pgsql') {
            return collect();
        }

        $query = Authority::query()
            ->current()
            ->whereNotNull('geom')
            ->withCount('applications');

        if ($state !== null && $state !== '') {
            $query->where('state', strtoupper($state));
        }

        return $query
            ->select('authorities.*')
            ->selectRaw('ROUND((ST_Area(authorities.geom::geography) / 1000000)::numeric, 0) AS area_km2')
            ->orderByDesc('area_km2')
            ->limit(max(1, min(25, $limit)))
            ->get();
    }
}
