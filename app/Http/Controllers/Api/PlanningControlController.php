<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouse\ListPlanningControlsRequest;
use App\Http\Requests\Warehouse\PlanningControlsAtPointRequest;
use App\Http\Resources\PlanningControlResource;
use App\Models\PlanningControl;
use App\Support\Warehouse\PlanningControlSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class PlanningControlController extends Controller
{
    public function __construct(
        private readonly PlanningControlSearch $planningControlSearch = new PlanningControlSearch,
    ) {}

    /**
     * Browse EPI / principal planning control polygons.
     *
     * Example: `GET /planning-controls?layer=zoning&code=R2&lga_name=Willoughby`
     */
    public function index(ListPlanningControlsRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->integer('per_page') ?: config('imby.list_per_page', 25));
        $search = $request->input('search', $request->input('filter'));
        $bounds = $request->input('bounds');
        $includeGeometry = $this->wantsGeometry($request);

        $query = $this->planningControlSearch->query(
            is_string($search) ? $search : null,
            $request->filled('layer') ? (string) $request->input('layer') : null,
            $request->filled('code') ? (string) $request->input('code') : null,
            $request->filled('epi_type') ? (string) $request->input('epi_type') : null,
            $request->filled('lga_name') ? (string) $request->input('lga_name') : null,
            $request->filled('authority_id') ? (int) $request->input('authority_id') : null,
            $request->filled('source') ? (string) $request->input('source') : null,
            is_array($bounds) && count($bounds) === 4 ? array_map('floatval', $bounds) : null,
        );

        if ($includeGeometry) {
            $query = $this->planningControlSearch->withGeometry($query);
        }

        $query = $this->planningControlSearch->ordered($query, (string) $request->input('order', 'layer'));

        return PlanningControlResource::collection($query->paginate($perPage));
    }

    /**
     * Fetch one planning control (geometry included by default).
     */
    public function show(Request $request, PlanningControl $planningControl): PlanningControlResource|JsonResponse
    {
        $query = PlanningControl::query()->whereKey($planningControl->id)->whereNotNull('geom');
        $query = $this->planningControlSearch->withGeometry($query);
        $row = $query->first();

        if ($row === null) {
            return response()->json(['message' => 'planning_control_not_found'], 404);
        }

        $request->merge(['include_geometry' => true]);

        return new PlanningControlResource($row);
    }

    /**
     * Planning controls that contain a WGS84 point (point-in-polygon).
     *
     * Example: `GET /planning-controls/at-point?lat=-33.796&lng=151.183&layers[]=zoning`
     */
    public function atPoint(PlanningControlsAtPointRequest $request): AnonymousResourceCollection|JsonResponse
    {
        $includeGeometry = $this->wantsGeometry($request);

        try {
            $query = $this->planningControlSearch->atPoint(
                (float) $request->input('lat'),
                (float) $request->input('lng'),
                $request->layers(),
                $request->filled('code') ? (string) $request->input('code') : null,
                (int) ($request->integer('limit') ?: 50),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($includeGeometry) {
            $query = $this->planningControlSearch->withGeometry($query);
        }

        $rows = $query->get();

        return PlanningControlResource::collection($rows)->additional([
            'meta' => [
                'lat' => (float) $request->input('lat'),
                'lng' => (float) $request->input('lng'),
                'layers' => $request->layers(),
                'count' => $rows->count(),
            ],
        ]);
    }

    /**
     * Distinct planning layers present in the warehouse.
     */
    public function layers(): JsonResponse
    {
        return response()->json([
            'message' => 'planning_layers',
            'data' => $this->planningControlSearch->layers(),
        ]);
    }

    /**
     * Distinct planning codes (optionally scoped to a layer / LGA).
     *
     * Example: `GET /taxonomies/planning-codes?layer=zoning&lga_name=Willoughby`
     */
    public function codes(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->input('limit', 200), 1), 500);

        return response()->json([
            'message' => 'planning_codes',
            'data' => $this->planningControlSearch->codes(
                $request->filled('layer') ? (string) $request->input('layer') : null,
                $request->filled('lga_name') ? (string) $request->input('lga_name') : null,
                $limit,
            ),
        ]);
    }

    private function wantsGeometry(Request $request): bool
    {
        return $request->boolean('include_geometry')
            || $request->boolean('geometry')
            || $request->input('include') === 'geometry';
    }
}
