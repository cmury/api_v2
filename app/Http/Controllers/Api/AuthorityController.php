<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ListAuthoritiesRequest;
use App\Http\Resources\AuthorityResource;
use App\Models\Authority;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
