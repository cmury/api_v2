<?php

namespace App\Support\Warehouse;

/**
 * Builds GeoJSON FeatureCollections for map / notification responses.
 */
final class GeoJson
{
    /**
     * @param  iterable<int, object|array<string, mixed>>  $rows
     * @return array{type: string, features: list<array<string, mixed>>}
     */
    public static function featureCollection(iterable $rows): array
    {
        $features = [];

        foreach ($rows as $row) {
            $feature = self::pointFeature($row);
            if ($feature !== null) {
                $features[] = $feature;
            }
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /**
     * @param  object|array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public static function pointFeature(object|array $row): ?array
    {
        // Eloquent models hide attributes behind (array) casts — use getAttributes().
        if (is_array($row)) {
            $data = $row;
        } elseif (method_exists($row, 'getAttributes')) {
            $data = $row->getAttributes();
            // selectRaw aliases (lat/lng) are present as dynamic props on the model.
            foreach (['lat', 'lng', 'formatted_address', 'submitted', 'application_count', 'id', 'location_id', 'location'] as $key) {
                if (! array_key_exists($key, $data) && isset($row->{$key})) {
                    $data[$key] = $row->{$key};
                }
            }
        } else {
            $data = (array) $row;
        }

        $lng = isset($data['lng']) ? (float) $data['lng'] : null;
        $lat = isset($data['lat']) ? (float) $data['lat'] : null;

        if ($lng === null || $lat === null) {
            return null;
        }

        $address = isset($data['formatted_address'])
            ? self::stripCountry((string) $data['formatted_address'])
            : (isset($data['location']) ? (string) $data['location'] : null);

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$lng, $lat],
            ],
            'properties' => [
                'location_id' => isset($data['id']) ? (int) $data['id'] : (isset($data['location_id']) ? (int) $data['location_id'] : null),
                'location' => $address,
                'submitted' => $data['submitted'] ?? null,
                'application_count' => isset($data['application_count'])
                    ? (int) $data['application_count']
                    : null,
            ],
        ];
    }

    public static function stripCountry(?string $address): ?string
    {
        if ($address === null || $address === '') {
            return $address;
        }

        $parts = array_map('trim', explode(',', $address));
        if (count($parts) > 1) {
            $last = strtolower((string) end($parts));
            if (in_array($last, ['australia', 'au'], true)) {
                array_pop($parts);
            }
        }

        return implode(', ', $parts);
    }
}
