<?php

namespace App\Support\Warehouse;

use App\Models\Location;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Location marker queries for the map (and notification) GeoJSON endpoints.
 */
final class MapMarkerQuery
{
    public function __construct(
        private readonly ApplicationQuery $applicationQuery = new ApplicationQuery,
    ) {}

    /**
     * @return Collection<int, object>
     */
    public function search(ApplicationFilter $filter, ?int $limit = null): Collection
    {
        $limit ??= (int) config('imby.marker_limit', 500);

        $query = Location::query()
            ->select([
                'locations.id',
                'locations.formatted_address',
            ])
            ->selectRaw('ST_Y(locations.geom::geometry) AS lat')
            ->selectRaw('ST_X(locations.geom::geometry) AS lng')
            ->selectRaw('MAX(a.submitted) AS submitted')
            ->selectRaw('COUNT(DISTINCT a.id) AS application_count')
            ->join('application_locations as al', 'al.location_id', '=', 'locations.id')
            ->join('applications as a', 'a.id', '=', 'al.application_id')
            ->whereNotNull('locations.geom')
            ->groupBy('locations.id', 'locations.formatted_address', 'locations.geom')
            ->orderByDesc(DB::raw('MAX(a.submitted)'))
            ->limit($limit);

        // Spatial + application filters: apply spatial on locations, app filters on `a`.
        $spatialOnly = new ApplicationFilter(
            bounds: $filter->bounds,
            centerLat: $filter->centerLat,
            centerLng: $filter->centerLng,
            radiusMeters: $filter->radiusMeters,
        );
        $this->applicationQuery->applySpatialToLocationsAlias($query, $spatialOnly, 'locations');

        $appOnly = new ApplicationFilter(
            applicationClassIds: $filter->applicationClassIds,
            developmentClassIds: $filter->developmentClassIds,
            decisionClassIds: $filter->decisionClassIds,
            estimatedCostMin: $filter->estimatedCostMin,
            estimatedCostMax: $filter->estimatedCostMax,
            submittedFrom: $filter->submittedFrom,
            submittedTo: $filter->submittedTo,
            state: $filter->state,
            authorityId: $filter->authorityId,
            search: $filter->search,
            includeAmalgamated: $filter->includeAmalgamated,
            legislationIds: $filter->legislationIds,
            suburb: $filter->suburb,
            applicationTypeIds: $filter->applicationTypeIds,
            developmentTypeIds: $filter->developmentTypeIds,
            decisionTypeIds: $filter->decisionTypeIds,
            source: $filter->source,
        );
        $this->applicationQuery->applyToApplications($query, $appOnly, 'a');

        return $query->get();
    }
}
