<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Warehouse\TaxonomyCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Taxonomy lists used by map filters (replaces old per-state type endpoints).
 */
class TaxonomyController extends Controller
{
    public function __construct(
        private readonly TaxonomyCatalog $taxonomyCatalog = new TaxonomyCatalog,
    ) {}

    public function applicationClasses(Request $request): JsonResponse
    {
        return $this->taxonomyResponse('application_classes', $request);
    }

    public function developmentClasses(Request $request): JsonResponse
    {
        return $this->taxonomyResponse('development_classes', $request);
    }

    public function decisionClasses(Request $request): JsonResponse
    {
        return $this->taxonomyResponse('decision_classes', $request);
    }

    public function applicationTypes(Request $request): JsonResponse
    {
        return $this->taxonomyResponse('application_types', $request);
    }

    public function developmentTypes(Request $request): JsonResponse
    {
        return $this->taxonomyResponse('development_types', $request);
    }

    public function decisionTypes(Request $request): JsonResponse
    {
        return $this->taxonomyResponse('decision_types', $request);
    }

    private function taxonomyResponse(string $kind, Request $request): JsonResponse
    {
        try {
            $data = $this->taxonomyCatalog->list(
                $kind,
                $request->filled('jurisdiction') ? (string) $request->input('jurisdiction') : null,
                $request->filled('class_id') ? (int) $request->input('class_id') : null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $kind,
            'data' => $data,
        ]);
    }
}
