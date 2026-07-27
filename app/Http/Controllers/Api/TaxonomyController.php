<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApplicationClass;
use App\Models\ApplicationType;
use App\Models\DecisionClass;
use App\Models\DecisionType;
use App\Models\DevelopmentClass;
use App\Models\DevelopmentType;
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

    public function applicationTypes(Request $request): JsonResponse
    {
        $query = ApplicationType::query()->orderBy('name');
        if ($request->filled('jurisdiction')) {
            $query->where('jurisdiction', strtoupper((string) $request->input('jurisdiction')));
        }
        if ($request->filled('class_id')) {
            $query->where('application_class_id', (int) $request->input('class_id'));
        }

        return response()->json([
            'message' => 'application_types',
            'data' => $query->get(['id', 'name', 'display_name', 'application_class_id', 'jurisdiction']),
        ]);
    }

    public function developmentTypes(Request $request): JsonResponse
    {
        $query = DevelopmentType::query()->orderBy('name');
        if ($request->filled('jurisdiction')) {
            $query->where('jurisdiction', strtoupper((string) $request->input('jurisdiction')));
        }
        if ($request->filled('class_id')) {
            $query->where('development_class_id', (int) $request->input('class_id'));
        }

        return response()->json([
            'message' => 'development_types',
            'data' => $query->get(['id', 'name', 'display_name', 'development_class_id', 'jurisdiction']),
        ]);
    }

    public function decisionTypes(Request $request): JsonResponse
    {
        $query = DecisionType::query()->orderBy('name');
        if ($request->filled('jurisdiction')) {
            $query->where('jurisdiction', strtoupper((string) $request->input('jurisdiction')));
        }
        if ($request->filled('class_id')) {
            $query->where('decision_class_id', (int) $request->input('class_id'));
        }

        return response()->json([
            'message' => 'decision_types',
            'data' => $query->get(['id', 'name', 'display_name', 'decision_class_id', 'jurisdiction']),
        ]);
    }
}
