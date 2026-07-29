<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ApplicationsNearTransitRequest;
use App\Http\Requests\Warehouse\ListTransitStopsRequest;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\TransitStopResource;
use App\Models\Application;
use App\Models\TransitStop;
use App\Support\Warehouse\ApplicationsNearTransit;
use App\Support\Warehouse\TransitStopSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class TransitController extends Controller
{
    public function __construct(
        private readonly TransitStopSearch $transitStopSearch = new TransitStopSearch,
        private readonly ApplicationsNearTransit $applicationsNearTransit = new ApplicationsNearTransit,
    ) {}

    /**
     * Search NSW transport facilities (transit_stops).
     *
     * Example: `GET /transit/stops?search=Chatswood&stop_type=train&state=NSW`
     */
    public function index(ListTransitStopsRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->integer('per_page') ?: config('imby.list_per_page', 25));
        $search = $request->input('search', $request->input('filter'));

        $query = $this->transitStopSearch->query(
            is_string($search) ? $search : null,
            $request->filled('state') ? (string) $request->input('state') : null,
            $request->filled('stop_type') ? (string) $request->input('stop_type') : null,
            $request->filled('operational_status') ? (string) $request->input('operational_status') : null,
        );

        $query = $this->transitStopSearch->ordered($query, (string) $request->input('order', 'name'));

        return TransitStopResource::collection($query->paginate($perPage));
    }

    /**
     * Fetch one transit stop with coordinates.
     */
    public function show(TransitStop $transitStop): TransitStopResource|JsonResponse
    {
        try {
            $stop = $this->applicationsNearTransit->withCoordinates($transitStop);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return new TransitStopResource($stop);
    }

    /**
     * Applications within a radius of a known transit stop.
     *
     * Example: `GET /transit/stops/42/applications?radius=1000&development_class_ids[]=3`
     */
    public function applications(ApplicationsNearTransitRequest $request, TransitStop $transitStop): AnonymousResourceCollection|JsonResponse
    {
        $request->merge(['transit_stop_id' => $transitStop->id]);

        return $this->near($request);
    }

    /**
     * Applications near a transit stop resolved by id or name search.
     *
     * Example:
     * `GET /transit/applications-near?stop_search=Chatswood Railway Station&radius=1000&application_class_ids[]=1`
     */
    public function near(ApplicationsNearTransitRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $perPage = (int) ($request->integer('per_page') ?: config('imby.list_per_page', 25));

        try {
            $resolved = $this->applicationsNearTransit->query(
                $request->applicationFilterInput(),
                $request->radiusMeters(),
                $request->filled('transit_stop_id') ? (int) $request->input('transit_stop_id') : null,
                $request->filled('stop_search') ? (string) $request->input('stop_search') : null,
                $request->filled('stop_type') ? (string) $request->input('stop_type') : null,
                $request->filled('state') ? (string) $request->input('state') : null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        /** @var Builder<Application> $query */
        $query = $resolved['query'];
        [$column, $direction] = $this->parseOrder($request->input('order', '-submitted'));
        $allowed = ['submitted', 'estimated_cost', 'created_at', 'authority_no', 'portal_no'];
        if (! in_array($column, $allowed, true)) {
            $column = 'submitted';
        }
        $query->orderBy($column, $direction);

        $collection = ApplicationResource::collection($query->paginate($perPage));
        $collection->additional([
            'meta' => [
                'transit_stop' => $resolved['stop'],
                'radius_meters' => $resolved['radius_meters'],
            ],
        ]);

        return $collection;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseOrder(mixed $order): array
    {
        $order = (string) ($order ?: '-submitted');
        if (str_starts_with($order, '-')) {
            return [substr($order, 1), 'desc'];
        }

        return [$order, 'asc'];
    }
}
