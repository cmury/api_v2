<?php

namespace App\Support\Warehouse;

use App\Models\Authority;

/**
 * Builds GeoJSON for an authority LGA boundary from PostGIS geom.
 */
final class AuthorityBoundary
{
    /**
     * @return array{type: string, geometry: array<string, mixed>, properties: array<string, mixed>}|null
     */
    public function feature(Authority $authority): ?array
    {
        $row = Authority::query()
            ->whereKey($authority->id)
            ->whereNotNull('geom')
            ->selectRaw('ST_AsGeoJSON(geom::geometry) AS geometry')
            ->first();

        if ($row === null || empty($row->geometry)) {
            return null;
        }

        $geometry = json_decode((string) $row->geometry, true);
        if (! is_array($geometry)) {
            return null;
        }

        return [
            'type' => 'Feature',
            'geometry' => $geometry,
            'properties' => [
                'authority_id' => $authority->id,
                'name' => $authority->name,
                'state' => $authority->state,
                'region' => $authority->region,
                'statistics_code' => $authority->statistics_code,
            ],
        ];
    }
}
