<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ListApplicationsRequest;
use App\Http\Requests\Warehouse\ListAuthoritiesRequest;
use App\Http\Requests\Warehouse\ListAuthorityStatisticsRequest;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\AuthorityResource;
use App\Http\Resources\AuthorityStatisticResource;
use App\Http\Resources\LocationResource;
use App\Models\Application;
use App\Models\Authority;
use App\Models\AuthorityStatistic;
use App\Models\Location;
use App\Support\Warehouse\ApplicationFilter;
use App\Support\Warehouse\ApplicationQuery;
use App\Support\Warehouse\AuthorityBoundary;
use App\Support\Warehouse\AuthoritySearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AuthorityController extends Controller
{
    public function __construct(
        private readonly AuthorityBoundary $authorityBoundary = new AuthorityBoundary,
        private readonly AuthoritySearch $authoritySearch = new AuthoritySearch,
        private readonly ApplicationQuery $applicationQuery = new ApplicationQuery,
    ) {}

    public function index(ListAuthoritiesRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->integer('per_page') ?: config('imby.list_per_page', 25));
        $search = $request->input('search', $request->input('filter'));

        $query = $this->authoritySearch->query(
            is_string($search) ? $search : null,
            $request->filled('state') ? (string) $request->input('state') : null,
            $request->boolean('amalgamated'),
        );

        $query = $this->authoritySearch->ordered($query, (string) $request->input('order', 'name'));

        return AuthorityResource::collection($query->paginate($perPage));
    }

    public function show(Authority $authority): AuthorityResource
    {
        $authority->loadCount('applications');

        return new AuthorityResource($authority);
    }

    /**
     * Search rows in authorities_statistics (cross-authority catalog).
     */
    public function statisticsIndex(ListAuthorityStatisticsRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->integer('per_page') ?: config('imby.list_per_page', 25));
        $statisticsCodes = $this->statisticsCodesForRequest($request);

        if ($request->boolean('latest')) {
            $latestYears = AuthorityStatistic::query()
                ->when($statisticsCodes !== null, fn ($q) => $q->whereIn('statistics_code', $statisticsCodes))
                ->select('statistics_code', 'measure', DB::raw('MAX(year) as max_year'))
                ->groupBy('statistics_code', 'measure');

            $query = AuthorityStatistic::query()
                ->from('authorities_statistics as s')
                ->joinSub($latestYears, 'latest', function ($join): void {
                    $join->on('s.statistics_code', '=', 'latest.statistics_code')
                        ->on('s.measure', '=', 'latest.measure')
                        ->on('s.year', '=', 'latest.max_year');
                })
                ->select('s.*')
                ->orderBy('s.statistics_code')
                ->orderBy('s.measure');

            $this->applyStatisticRowFilters($query, $request, 's');
        } else {
            $query = AuthorityStatistic::query()
                ->when($statisticsCodes !== null, fn ($q) => $q->whereIn('statistics_code', $statisticsCodes))
                ->orderBy('statistics_code')
                ->orderBy('measure')
                ->orderByDesc('year');

            $this->applyStatisticRowFilters($query, $request);
        }

        return AuthorityStatisticResource::collection($query->paginate($perPage));
    }

    /**
     * ABS / census statistics for an authority (latest year per measure by default).
     *
     * Query: ?all=1 to return every year; ?year=2021 to pin a year.
     */
    public function statistics(Request $request, Authority $authority): JsonResponse
    {
        $code = $authority->statistics_code;

        if ($code === null) {
            return response()->json([
                'message' => 'authority_statistics',
                'statistics' => [],
                'data' => [],
            ]);
        }

        $base = AuthorityStatistic::query()->where('statistics_code', $code);

        if ($request->filled('year')) {
            $rows = (clone $base)
                ->where('year', (int) $request->input('year'))
                ->orderBy('measure')
                ->get();
        } elseif ($request->boolean('all')) {
            $rows = (clone $base)
                ->orderBy('measure')
                ->orderByDesc('year')
                ->get();
        } else {
            // Latest year per measure (matches old API behaviour).
            $latestYears = AuthorityStatistic::query()
                ->select('measure', DB::raw('MAX(year) as max_year'))
                ->where('statistics_code', $code)
                ->groupBy('measure');

            $rows = AuthorityStatistic::query()
                ->from('authorities_statistics as s')
                ->joinSub($latestYears, 'latest', function ($join): void {
                    $join->on('s.measure', '=', 'latest.measure')
                        ->on('s.year', '=', 'latest.max_year');
                })
                ->where('s.statistics_code', $code)
                ->orderBy('s.measure')
                ->select('s.*')
                ->get();
        }

        $payload = AuthorityStatisticResource::collection($rows);

        return response()->json([
            'message' => 'authority_statistics',
            // Legacy key expected by imby_v2 AuthorityService.
            'statistics' => $payload,
            'data' => $payload,
        ]);
    }

    public function locations(Request $request, Authority $authority): AnonymousResourceCollection
    {
        $perPage = min(
            max((int) $request->input('per_page', config('imby.list_per_page', 25)), 1),
            (int) config('imby.list_max_per_page', 100),
        );

        $locations = Location::query()
            ->join('authority_locations as al', 'al.location_id', '=', 'locations.id')
            ->where('al.authority_id', $authority->id)
            ->select('locations.*')
            ->selectRaw('ST_Y(locations.geom::geometry) AS lat')
            ->selectRaw('ST_X(locations.geom::geometry) AS lng')
            ->orderBy('locations.suburb')
            ->orderBy('locations.street')
            ->paginate($perPage);

        return LocationResource::collection($locations);
    }

    /**
     * Applications for an authority (same filters as GET /applications, authority fixed by route).
     */
    public function applications(ListApplicationsRequest $request, Authority $authority): AnonymousResourceCollection
    {
        $perPage = (int) ($request->integer('per_page') ?: config('imby.list_per_page', 25));
        $filter = ApplicationFilter::fromArray([
            ...$request->validated(),
            'authority_id' => $authority->id,
        ]);

        $query = Application::query()->with(['authority']);
        $this->applicationQuery->applyToApplications($query, $filter);

        [$column, $direction] = $this->parseApplicationOrder($request->input('order', '-submitted'));
        $allowed = ['submitted', 'estimated_cost', 'created_at', 'authority_no', 'portal_no'];
        if (! in_array($column, $allowed, true)) {
            $column = 'submitted';
        }

        $query->orderBy($column, $direction);

        return ApplicationResource::collection($query->paginate($perPage));
    }

    /**
     * Successor (amalgamated_into) and former councils (predecessors) for this authority.
     */
    public function amalgamation(Authority $authority): JsonResponse
    {
        $successor = $authority->amalgamatedInto()->first();
        $predecessors = $authority->predecessors()->orderBy('name')->get();

        return response()->json([
            'message' => 'authority_amalgamation',
            'data' => [
                'authority' => new AuthorityResource($authority),
                'amalgamated_into' => $successor ? new AuthorityResource($successor) : null,
                'predecessors' => AuthorityResource::collection($predecessors),
            ],
        ]);
    }

    public function boundary(Authority $authority): JsonResponse
    {
        $feature = $this->authorityBoundary->feature($authority);

        if ($feature === null) {
            return response()->json([
                'message' => 'authority_boundary_not_found',
            ], 404);
        }

        return response()->json([
            'message' => 'authority_boundary',
            'data' => $feature,
        ]);
    }

    public function coverage(): JsonResponse
    {
        $rows = Authority::query()
            ->current()
            ->where('tracking', true)
            ->select(['id', 'name', 'state', 'region', 'tracking_system'])
            ->orderBy('state')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'authorities_coverage',
            'data' => $rows,
        ]);
    }

    /**
     * @return array<int, int>|null
     */
    private function statisticsCodesForRequest(ListAuthorityStatisticsRequest $request): ?array
    {
        if ($request->filled('statistics_code')) {
            return [(int) $request->input('statistics_code')];
        }

        if ($request->filled('authority_id')) {
            $code = Authority::query()
                ->whereKey((int) $request->input('authority_id'))
                ->value('statistics_code');

            return $code !== null ? [(int) $code] : [];
        }

        if ($request->filled('state')) {
            return Authority::query()
                ->where('state', strtoupper((string) $request->input('state')))
                ->whereNotNull('statistics_code')
                ->pluck('statistics_code')
                ->map(fn ($code) => (int) $code)
                ->all();
        }

        return null;
    }

    private function applyStatisticRowFilters(
        Builder $query,
        ListAuthorityStatisticsRequest $request,
        ?string $alias = null,
    ): void {
        $column = static fn (string $name): string => $alias ? "{$alias}.{$name}" : $name;

        if ($request->filled('measure')) {
            $query->where($column('measure'), 'ilike', '%'.$request->input('measure').'%');
        }

        if ($request->filled('year')) {
            $query->where($column('year'), (int) $request->input('year'));
        }

        if ($request->filled('source')) {
            $query->where($column('source'), 'ilike', '%'.$request->input('source').'%');
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseApplicationOrder(mixed $order): array
    {
        $order = (string) ($order ?: '-submitted');
        if (str_starts_with($order, '-')) {
            return [substr($order, 1), 'desc'];
        }

        return [$order, 'asc'];
    }
}
