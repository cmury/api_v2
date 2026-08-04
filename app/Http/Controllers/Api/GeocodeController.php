<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Geocode\GeocodeReverseRequest;
use App\Http\Requests\Geocode\GeocodeSearchRequest;
use App\Support\Geocoding\NominatimGeocoder;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Throwable;

class GeocodeController extends Controller
{
    public function __construct(
        private readonly NominatimGeocoder $geocoder = new NominatimGeocoder,
    ) {}

    public function search(GeocodeSearchRequest $request): JsonResponse
    {
        $limit = (int) ($request->integer('limit') ?: 5);

        try {
            $results = $this->geocoder->search((string) $request->input('q'), $limit);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'geocode_unavailable',
            ], 502);
        }

        return response()->json(['data' => $results]);
    }

    public function reverse(GeocodeReverseRequest $request): JsonResponse
    {
        try {
            $result = $this->geocoder->reverse(
                (float) $request->input('lat'),
                (float) $request->input('lng'),
            );
        } catch (RuntimeException $e) {
            report($e);

            return response()->json([
                'message' => 'geocode_unavailable',
            ], 502);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'geocode_unavailable',
            ], 502);
        }

        if ($result === null) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $result]);
    }
}
