<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ListLocationsRequest;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\LocationResource;
use App\Models\Application;
use App\Models\Location;
use App\Support\Warehouse\ApplicationFilter;
use App\Support\Warehouse\ApplicationQuery;
use App\Support\Warehouse\LocationSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LocationController extends Controller
{
    public function __construct(
        private readonly ApplicationQuery $applicationQuery = new ApplicationQuery,
        private readonly LocationSearch $locationSearch = new LocationSearch,
    ) {}

    public function index(ListLocationsRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->integer('per_page') ?: config('imby.list_per_page', 25));
        $search = $request->input('search', $request->input('filter'));

        $query = $this->locationSearch->query(
            is_string($search) ? $search : null,
            $request->filled('state') ? (string) $request->input('state') : null,
            $request->filled('suburb') ? (string) $request->input('suburb') : null,
            $request->filled('authority_id') ? (int) $request->input('authority_id') : null,
        );

        $query = $this->locationSearch->ordered($query, (string) $request->input('order', 'suburb'));

        return LocationResource::collection($query->paginate($perPage));
    }

    public function show(Location $location): LocationResource
    {
        $location->load(['applications' => fn ($q) => $q->latest('submitted')->limit(50)]);

        $coords = Location::query()
            ->whereKey($location->id)
            ->whereNotNull('geom')
            ->selectRaw('ST_Y(geom::geometry) AS lat, ST_X(geom::geometry) AS lng')
            ->first();

        if ($coords) {
            $location->setAttribute('lat', $coords->lat);
            $location->setAttribute('lng', $coords->lng);
        }

        return new LocationResource($location);
    }

    public function applications(Request $request, Location $location): AnonymousResourceCollection
    {
        $perPage = min(
            max((int) $request->input('per_page', config('imby.list_per_page', 25)), 1),
            (int) config('imby.list_max_per_page', 100),
        );

        $filter = ApplicationFilter::fromArray([
            ...$request->all(),
            'location_id' => $location->id,
        ]);

        $query = Application::query()->with(['authority']);
        $this->applicationQuery->applyToApplications($query, $filter);
        $query->orderByDesc('submitted');

        return ApplicationResource::collection($query->paginate($perPage));
    }
}
