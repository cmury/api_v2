<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ApplicationsNearFacilityRequest;
use App\Http\Requests\Warehouse\ListFacilitiesRequest;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\FacilityResource;
use App\Models\Application;
use App\Models\Facility;
use App\Support\Warehouse\ApplicationsNearFacility;
use App\Support\Warehouse\FacilitySearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class FacilityController extends Controller
{
    public function __construct(
        private readonly FacilitySearch $facilitySearch = new FacilitySearch,
        private readonly ApplicationsNearFacility $applicationsNearFacility = new ApplicationsNearFacility,
    ) {}

    /**
     * Search point facilities (transport, education, …).
     *
     * Example: `GET /facilities?search=Chatswood&facility_type=train&state=NSW`
     */
    public function index(ListFacilitiesRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->integer('per_page') ?: config('imby.list_per_page', 25));
        $search = $request->input('search', $request->input('filter'));

        $query = $this->facilitySearch->query(
            is_string($search) ? $search : null,
            $request->filled('state') ? (string) $request->input('state') : null,
            $request->filled('facility_type') ? (string) $request->input('facility_type') : null,
            $request->filled('operational_status') ? (string) $request->input('operational_status') : null,
        );

        $query = $this->facilitySearch->ordered($query, (string) $request->input('order', 'name'));

        return FacilityResource::collection($query->paginate($perPage));
    }

    /**
     * Fetch one facility with coordinates.
     */
    public function show(Facility $facility): FacilityResource|JsonResponse
    {
        try {
            $facility = $this->applicationsNearFacility->withCoordinates($facility);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return new FacilityResource($facility);
    }

    /**
     * Applications within a radius of a known facility.
     *
     * Example: `GET /facilities/42/applications?radius=1000&development_class_ids[]=3`
     */
    public function applications(ApplicationsNearFacilityRequest $request, Facility $facility): AnonymousResourceCollection|JsonResponse
    {
        $request->merge(['facility_id' => $facility->id]);

        return $this->near($request);
    }

    /**
     * Applications near a facility resolved by id or name search.
     *
     * Example:
     * `GET /facilities/applications-near?facility_search=Chatswood Railway Station&radius=1000&application_class_ids[]=1`
     */
    public function near(ApplicationsNearFacilityRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $perPage = (int) ($request->integer('per_page') ?: config('imby.list_per_page', 25));

        try {
            $resolved = $this->applicationsNearFacility->query(
                $request->applicationFilterInput(),
                $request->radiusMeters(),
                $request->facilityId(),
                $request->facilitySearch(),
                $request->filled('facility_type') ? (string) $request->input('facility_type') : null,
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
                'facility' => $resolved['facility'],
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
