<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ListAuthoritiesRequest;
use App\Http\Resources\AuthorityResource;
use App\Http\Resources\AuthorityStatisticResource;
use App\Models\Authority;
use App\Models\AuthorityStatistic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AuthorityController extends Controller
{
    public function index(ListAuthoritiesRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->integer('per_page') ?: config('imby.list_per_page', 25));
        $search = $request->input('search', $request->input('filter'));
        $includeAmalgamated = $request->boolean('amalgamated');

        $query = Authority::query()->withCount('applications');

        if (! $includeAmalgamated) {
            $query->current();
        }

        if ($request->filled('state')) {
            $query->where('state', strtoupper((string) $request->input('state')));
        }

        if (is_string($search) && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('name', 'ilike', $like)
                    ->orWhere('region', 'ilike', $like)
                    ->orWhere('state', 'ilike', $like)
                    ->orWhere('tracking_system', 'ilike', $like);
            });
        }

        [$column, $direction] = $this->parseOrder($request->input('order', 'name'));
        $allowed = ['name', 'state', 'region', 'created_at', 'applications_count'];
        if (! in_array($column, $allowed, true)) {
            $column = 'name';
        }

        $query->orderBy($column, $direction);

        return AuthorityResource::collection($query->paginate($perPage));
    }

    public function show(Authority $authority): AuthorityResource
    {
        $authority->loadCount('applications');

        return new AuthorityResource($authority);
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
     * @return array{0: string, 1: string}
     */
    private function parseOrder(mixed $order): array
    {
        $order = (string) ($order ?: 'name');
        if (str_starts_with($order, '-')) {
            return [substr($order, 1), 'desc'];
        }

        return [$order, 'asc'];
    }
}
