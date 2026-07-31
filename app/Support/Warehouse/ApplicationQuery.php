<?php

namespace App\Support\Warehouse;

use App\Models\Application;
use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Applies {@see ApplicationFilter} to application / location queries.
 */
final class ApplicationQuery
{
    /**
     * Constrain an applications Eloquent/query builder.
     *
     * @param  Builder<Model>|QueryBuilder  $query
     * @param  string|null  $appsTable  Table/alias for applications columns (default: model table or "applications")
     */
    public function applyToApplications(Builder|QueryBuilder $query, ApplicationFilter $filter, ?string $appsTable = null): void
    {
        $appsTable ??= $query instanceof Builder && $query->getModel() instanceof Application
            ? $query->getModel()->getTable()
            : 'applications';

        if ($filter->authorityId !== null) {
            $query->where("{$appsTable}.authority_id", $filter->authorityId);
        }

        if ($filter->source !== null) {
            $query->where("{$appsTable}.source", $filter->source);
        }

        if ($filter->state !== null) {
            $query->whereExists(function ($q) use ($filter, $appsTable): void {
                $q->selectRaw('1')
                    ->from('authorities')
                    ->whereColumn('authorities.id', "{$appsTable}.authority_id")
                    ->where('authorities.state', $filter->state);

                if (! $filter->includeAmalgamated) {
                    $q->whereNull('authorities.amalgamated');
                }
            });
        }

        if ($filter->locationId !== null) {
            $query->whereExists(function ($q) use ($filter, $appsTable): void {
                $q->selectRaw('1')
                    ->from('application_locations')
                    ->whereColumn('application_locations.application_id', "{$appsTable}.id")
                    ->where('application_locations.location_id', $filter->locationId);
            });
        }

        if ($filter->suburb !== null) {
            $query->whereExists(function ($q) use ($filter, $appsTable): void {
                $q->selectRaw('1')
                    ->from('application_locations as al_sub')
                    ->join('locations as l_sub', 'l_sub.id', '=', 'al_sub.location_id')
                    ->whereColumn('al_sub.application_id', "{$appsTable}.id")
                    ->where('l_sub.suburb', 'ilike', $filter->suburb);
            });
        }

        if ($filter->submittedFrom !== null) {
            $query->whereDate("{$appsTable}.submitted", '>=', $filter->submittedFrom->toDateString());
        }

        if ($filter->submittedTo !== null) {
            $query->whereDate("{$appsTable}.submitted", '<=', $filter->submittedTo->toDateString());
        }

        if ($filter->estimatedCostMin !== null) {
            $query->where("{$appsTable}.estimated_cost", '>=', $filter->estimatedCostMin);
        }

        if ($filter->estimatedCostMax !== null) {
            $query->where("{$appsTable}.estimated_cost", '<=', $filter->estimatedCostMax);
        }

        if ($filter->search !== null) {
            $like = '%'.$filter->search.'%';
            $query->where(function ($q) use ($like, $appsTable): void {
                $q->where("{$appsTable}.description", 'ilike', $like)
                    ->orWhere("{$appsTable}.authority_no", 'ilike', $like)
                    ->orWhere("{$appsTable}.portal_no", 'ilike', $like)
                    ->orWhere("{$appsTable}.type", 'ilike', $like);
            });
        }

        if ($filter->applicationClassIds !== null) {
            $query->whereExists(function ($q) use ($filter, $appsTable): void {
                $q->selectRaw('1')
                    ->from('application_application_types as aat')
                    ->join('application_types as at', 'at.id', '=', 'aat.application_type_id')
                    ->whereColumn('aat.application_id', "{$appsTable}.id")
                    ->whereIn('at.application_class_id', $filter->applicationClassIds);
            });
        }

        if ($filter->developmentClassIds !== null) {
            $query->whereExists(function ($q) use ($filter, $appsTable): void {
                $q->selectRaw('1')
                    ->from('application_development_types as adt')
                    ->join('development_types as dt', 'dt.id', '=', 'adt.development_type_id')
                    ->whereColumn('adt.application_id', "{$appsTable}.id")
                    ->whereIn('dt.development_class_id', $filter->developmentClassIds);
            });
        }

        if ($filter->decisionClassIds !== null) {
            $query->whereExists(function ($q) use ($filter, $appsTable): void {
                $q->selectRaw('1')
                    ->from('application_decision_types as adct')
                    ->join('decision_types as dct', 'dct.id', '=', 'adct.decision_type_id')
                    ->whereColumn('adct.application_id', "{$appsTable}.id")
                    ->whereIn('dct.decision_class_id', $filter->decisionClassIds);
            });
        }

        if ($filter->applicationTypeIds !== null) {
            $query->whereExists(function ($q) use ($filter, $appsTable): void {
                $q->selectRaw('1')
                    ->from('application_application_types as aat')
                    ->whereColumn('aat.application_id', "{$appsTable}.id")
                    ->whereIn('aat.application_type_id', $filter->applicationTypeIds);
            });
        }

        if ($filter->developmentTypeIds !== null) {
            $query->whereExists(function ($q) use ($filter, $appsTable): void {
                $q->selectRaw('1')
                    ->from('application_development_types as adt')
                    ->whereColumn('adt.application_id', "{$appsTable}.id")
                    ->whereIn('adt.development_type_id', $filter->developmentTypeIds);
            });
        }

        if ($filter->decisionTypeIds !== null) {
            $query->whereExists(function ($q) use ($filter, $appsTable): void {
                $q->selectRaw('1')
                    ->from('application_decision_types as adct')
                    ->whereColumn('adct.application_id', "{$appsTable}.id")
                    ->whereIn('adct.decision_type_id', $filter->decisionTypeIds);
            });
        }

        if ($filter->legislationIds !== null) {
            $query->whereExists(function ($q) use ($filter, $appsTable): void {
                $q->selectRaw('1')
                    ->from('application_legislation as al')
                    ->whereColumn('al.application_id', "{$appsTable}.id")
                    ->whereIn('al.legislation_id', $filter->legislationIds);
            });
        }

        if ($filter->bounds !== null || $this->hasRadius($filter)) {
            $query->whereExists(function ($q) use ($filter, $appsTable): void {
                $q->selectRaw('1')
                    ->from('application_locations as al')
                    ->join('locations as l', 'l.id', '=', 'al.location_id')
                    ->whereColumn('al.application_id', "{$appsTable}.id")
                    ->whereNotNull('l.geom');

                $this->applySpatialConstraints($q, $filter, 'l');
            });
        }
    }

    /**
     * Constrain a locations query to those that have matching applications.
     *
     * @param  Builder<Location>|QueryBuilder  $query
     */
    public function applyToLocations(Builder|QueryBuilder $query, ApplicationFilter $filter): void
    {
        $locationsTable = $query instanceof Builder ? $query->getModel()->getTable() : 'locations';

        $query->whereNotNull("{$locationsTable}.geom");

        $this->applySpatialConstraints($query, $filter, $locationsTable);

        $query->whereExists(function ($q) use ($filter, $locationsTable): void {
            $q->selectRaw('1')
                ->from('application_locations as al')
                ->join('applications as a', 'a.id', '=', 'al.application_id')
                ->whereColumn('al.location_id', "{$locationsTable}.id");

            // Re-apply non-spatial application filters against alias `a`.
            $appFilter = new ApplicationFilter(
                bounds: null,
                applicationClassIds: $filter->applicationClassIds,
                developmentClassIds: $filter->developmentClassIds,
                decisionClassIds: $filter->decisionClassIds,
                estimatedCostMin: $filter->estimatedCostMin,
                estimatedCostMax: $filter->estimatedCostMax,
                submittedFrom: $filter->submittedFrom,
                submittedTo: $filter->submittedTo,
                state: $filter->state,
                authorityId: $filter->authorityId,
                locationId: null,
                search: $filter->search,
                includeAmalgamated: $filter->includeAmalgamated,
                legislationIds: $filter->legislationIds,
                suburb: $filter->suburb,
                applicationTypeIds: $filter->applicationTypeIds,
                developmentTypeIds: $filter->developmentTypeIds,
                decisionTypeIds: $filter->decisionTypeIds,
                source: $filter->source,
            );

            $this->applyToApplications($q, $appFilter, 'a');
        });
    }

    /**
     * Apply bbox / radius constraints against a locations table alias.
     *
     * @param  Builder<Model>|QueryBuilder  $query
     */
    public function applySpatialToLocationsAlias(Builder|QueryBuilder $query, ApplicationFilter $filter, string $alias): void
    {
        $this->applySpatialConstraints($query, $filter, $alias);
    }

    /**
     * @param  Builder<Model>|QueryBuilder  $query
     */
    private function applySpatialConstraints(Builder|QueryBuilder $query, ApplicationFilter $filter, string $alias): void
    {
        if ($filter->bounds !== null) {
            [$latMax, $lngMax, $latMin, $lngMin] = $filter->bounds;

            // Prefer envelope intersect when PostGIS geography is available.
            $query->whereRaw(
                "{$alias}.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography
                 AND ST_Intersects({$alias}.geom, ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography)",
                [$lngMin, $latMin, $lngMax, $latMax, $lngMin, $latMin, $lngMax, $latMax],
            );
        }

        if ($this->hasRadius($filter)) {
            $query->whereRaw(
                "ST_DWithin({$alias}.geom, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)",
                [$filter->centerLng, $filter->centerLat, $filter->radiusMeters],
            );
        }
    }

    private function hasRadius(ApplicationFilter $filter): bool
    {
        return $filter->centerLat !== null
            && $filter->centerLng !== null
            && $filter->radiusMeters !== null
            && $filter->radiusMeters > 0;
    }
}
