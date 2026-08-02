<?php

namespace App\Support\Warehouse;

use App\Models\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Application marker queries for the map (and notification) GeoJSON endpoints.
 * One feature per application (with a representative location for coordinates).
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

        $idsQuery = Application::query()
            ->from('applications as a')
            ->select('a.id')
            ->orderByDesc('a.submitted')
            ->orderByDesc('a.id')
            ->limit($limit);

        $this->applicationQuery->applyToApplications($idsQuery, $filter, 'a');

        $ids = $idsQuery->pluck('id');
        if ($ids->isEmpty()) {
            return collect();
        }

        $connection = Application::query()->getConnection()->getName();
        $idList = $ids->all();

        $rowsQuery = DB::connection($connection)
            ->table('applications as a')
            ->join('application_locations as al', 'al.application_id', '=', 'a.id')
            ->join('locations as l', 'l.id', '=', 'al.location_id')
            ->whereIn('a.id', $idList)
            ->whereNotNull('l.geom')
            ->select([
                'a.id',
                'a.portal_no',
                'a.authority_no',
                'a.type',
                'a.description',
                'a.estimated_cost',
                'a.submitted',
                'a.decision',
                'l.id as location_id',
                'l.formatted_address',
            ])
            ->selectRaw('ST_Y(l.geom::geometry) AS lat')
            ->selectRaw('ST_X(l.geom::geometry) AS lng')
            ->orderByDesc('a.submitted')
            ->orderByDesc('a.id');

        // Prefer a location that falls inside the active spatial filter.
        $spatialOnly = new ApplicationFilter(
            bounds: $filter->bounds,
            centerLat: $filter->centerLat,
            centerLng: $filter->centerLng,
            radiusMeters: $filter->radiusMeters,
        );
        $this->applicationQuery->applySpatialToLocationsAlias($rowsQuery, $spatialOnly, 'l');

        $rows = $rowsQuery
            ->get()
            ->unique('id')
            ->values();

        $rowIds = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();

        $classesByApplication = DB::connection($connection)
            ->table('application_development_types as adt')
            ->join('development_types as dt', 'dt.id', '=', 'adt.development_type_id')
            ->join('development_classes as dc', 'dc.id', '=', 'dt.development_class_id')
            ->whereIn('adt.application_id', $rowIds)
            ->select([
                'adt.application_id',
                'dc.id',
                'dc.development_class',
                'dc.name',
                'dc.description',
                'dc.icon',
                'dc.icon_priority',
            ])
            ->distinct()
            ->get()
            ->groupBy(fn (object $row) => (int) $row->application_id);

        $decisionClassesByApplication = DB::connection($connection)
            ->table('application_decision_types as adt')
            ->join('decision_types as dt', 'dt.id', '=', 'adt.decision_type_id')
            ->join('decision_classes as dc', 'dc.id', '=', 'dt.decision_class_id')
            ->whereIn('adt.application_id', $rowIds)
            ->select([
                'adt.application_id',
                'dc.id',
                'dc.name',
                'dc.display_name',
                'dc.description',
            ])
            ->distinct()
            ->get()
            ->groupBy(fn (object $row) => (int) $row->application_id);

        return $rows->map(function (object $row) use ($classesByApplication, $decisionClassesByApplication) {
            $applicationId = (int) $row->id;

            $row->development_classes = ($classesByApplication->get($applicationId) ?? collect())
                ->unique('id')
                ->values()
                ->map(static fn (object $class): array => [
                    'id' => (int) $class->id,
                    'development_class' => $class->development_class,
                    'name' => $class->name,
                    'description' => $class->description,
                    'icon' => $class->icon,
                    'icon_priority' => (int) ($class->icon_priority ?? 0),
                ])
                ->all();

            $row->decision_classes = ($decisionClassesByApplication->get($applicationId) ?? collect())
                ->unique('id')
                ->values()
                ->map(static fn (object $class): array => [
                    'id' => (int) $class->id,
                    'name' => $class->display_name ?: $class->name,
                    'description' => $class->description,
                ])
                ->all();

            return $row;
        });
    }
}
