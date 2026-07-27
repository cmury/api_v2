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
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LocationController extends Controller
{
    public function __construct(
        private readonly ApplicationQuery $applicationQuery = new ApplicationQuery,
    ) {}

    public function index(ListLocationsRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->integer('per_page') ?: config('imby.list_per_page', 25));
        $search = $request->input('search', $request->input('filter'));

        $query = Location::query()
            ->withCount('applications')
            ->select('locations.*')
            ->selectRaw('ST_Y(locations.geom::geometry) AS lat')
            ->selectRaw('ST_X(locations.geom::geometry) AS lng');

        if ($request->filled('state')) {
            $query->where('locations.state', strtoupper((string) $request->input('state')));
        }

        if ($request->filled('suburb')) {
            $query->where('locations.suburb', 'ilike', (string) $request->input('suburb'));
        }

        if ($request->filled('authority_id')) {
            $query->join('authority_locations as al', 'al.location_id', '=', 'locations.id')
                ->where('al.authority_id', (int) $request->input('authority_id'));
        }

        if (is_string($search) && $search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('locations.formatted_address', 'ilike', $like)
                    ->orWhere('locations.street', 'ilike', $like)
                    ->orWhere('locations.suburb', 'ilike', $like)
                    ->orWhere('locations.post_code', 'ilike', $like)
                    ->orWhere('locations.state', 'ilike', $like);
            });
        }

        [$column, $direction] = $this->parseOrder($request->input('order', 'suburb'));
        $allowed = ['formatted_address', 'street', 'suburb', 'state', 'post_code', 'created_at', 'applications_count'];
        if (! in_array($column, $allowed, true)) {
            $column = 'suburb';
        }

        $orderColumn = $column === 'applications_count' ? $column : "locations.{$column}";
        $query->orderBy($orderColumn, $direction);

        if ($column !== 'street') {
            $query->orderBy('locations.street');
        }

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

    /**
     * @return array{0: string, 1: string}
     */
    private function parseOrder(mixed $order): array
    {
        $order = (string) ($order ?: 'suburb');
        if (str_starts_with($order, '-')) {
            return [substr($order, 1), 'desc'];
        }

        return [$order, 'asc'];
    }
}
