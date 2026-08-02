<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Map\MapMarkersRequest;
use App\Support\Warehouse\ApplicationFilter;
use App\Support\Warehouse\GeoJson;
use App\Support\Warehouse\MapCsvExport;
use App\Support\Warehouse\MapMarkerQuery;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MapController extends Controller
{
    public function __construct(
        private readonly MapMarkerQuery $mapMarkerQuery = new MapMarkerQuery,
        private readonly MapCsvExport $mapCsvExport = new MapCsvExport,
    ) {}

    /**
     * Public map markers (GeoJSON FeatureCollection) — one feature per application.
     * Accepts the old `?query=<json>` envelope or flat filter params.
     */
    public function markers(MapMarkersRequest $request): JsonResponse
    {
        $filter = ApplicationFilter::fromArray($request->filterPayload(), defaultDateWindow: true);
        $rows = $this->mapMarkerQuery->search($filter);

        return response()->json(GeoJson::featureCollection($rows));
    }

    /**
     * Authenticated CSV export of applications matching the same filters as map markers.
     */
    public function csv(MapMarkersRequest $request): StreamedResponse
    {
        $filter = ApplicationFilter::fromArray($request->filterPayload(), defaultDateWindow: true);

        return $this->mapCsvExport->stream($filter);
    }
}
