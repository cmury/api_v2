<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApplicationClass;
use App\Models\DecisionClass;
use App\Models\DevelopmentClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Taxonomy lists used by map filters (replaces old per-state type endpoints).
 */
class TaxonomyController extends Controller
{
    public function applicationClasses(Request $request): JsonResponse
    {
        $query = ApplicationClass::query()->orderBy('name');
        if ($request->filled('jurisdiction')) {
            $query->where('jurisdiction', strtoupper((string) $request->input('jurisdiction')));
        }

        return response()->json([
            'message' => 'application_classes',
            'data' => $query->get(['id', 'name', 'display_name', 'abbrev', 'jurisdiction', 'icon']),
        ]);
    }

    public function developmentClasses(Request $request): JsonResponse
    {
        $query = DevelopmentClass::query()->orderBy('name');
        if ($request->filled('jurisdiction')) {
            $query->where('jurisdiction', strtoupper((string) $request->input('jurisdiction')));
        }

        return response()->json([
            'message' => 'development_classes',
            'data' => $query->get(['id', 'name', 'display_name', 'abbrev', 'development_class', 'jurisdiction', 'icon']),
        ]);
    }

    public function decisionClasses(Request $request): JsonResponse
    {
        $query = DecisionClass::query()->orderBy('name');
        if ($request->filled('jurisdiction')) {
            $query->where('jurisdiction', strtoupper((string) $request->input('jurisdiction')));
        }

        return response()->json([
            'message' => 'decision_classes',
            'data' => $query->get(['id', 'name', 'display_name', 'abbrev', 'jurisdiction', 'icon']),
        ]);
    }
}
