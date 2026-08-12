<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Map\MapMarkersRequest;
use App\Models\User;
use App\Support\UserActivityLogger;
use App\Support\Warehouse\ApplicationFilter;
use App\Support\Warehouse\GeoJson;
use App\Support\Warehouse\MapCsvExport;
use App\Support\Warehouse\MapMarkerQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MapController extends Controller
{
    public function __construct(
        private readonly MapMarkerQuery $mapMarkerQuery = new MapMarkerQuery,
        private readonly MapCsvExport $mapCsvExport = new MapCsvExport,
        private readonly UserActivityLogger $activityLogger = new UserActivityLogger,
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
     * Lightweight public beacon when someone shares the current search / map URL.
     */
    public function share(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user('sanctum');
        $this->activityLogger->logSearchShare(
            $user instanceof User ? $user : null,
            $request,
            array_filter([
                'source' => $request->string('source')->toString() ?: null,
                'result' => $request->string('result')->toString() ?: null,
                'url' => $request->string('url')->toString() ?: null,
                'search' => $request->string('search')->toString() ?: null,
            ], static fn ($value) => $value !== null && $value !== ''),
        );

        return response()->json([
            'message' => 'search_shared',
        ]);
    }

    /**
     * Authenticated CSV export of applications matching the same filters as map markers.
     */
    public function csv(MapMarkersRequest $request): StreamedResponse
    {
        $filter = ApplicationFilter::fromArray($request->filterPayload(), defaultDateWindow: true);

        $user = $request->user();
        if ($user instanceof User) {
            $this->activityLogger->log(
                $user,
                UserActivityLogger::MAP_CSV_EXPORTED,
                [
                    'filter' => $request->filterPayload(),
                ],
            );
        }

        return $this->mapCsvExport->stream($filter);
    }
}
