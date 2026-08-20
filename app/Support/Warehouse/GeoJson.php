<?php

namespace App\Support\Warehouse;

use Carbon\Carbon;
use DateTimeInterface;

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
            foreach ([
                'lat', 'lng', 'formatted_address', 'submitted', 'created_at', 'application_count',
                'id', 'location_id', 'location', 'portal_no', 'authority_no', 'type',
                'description', 'decision', 'estimated_cost', 'development_classes',
                'decision_classes', 'street_no', 'street', 'suburb', 'state', 'post_code',
                'search_id', 'search_name',
            ] as $key) {
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

        $applicationId = isset($data['id']) ? (int) $data['id'] : null;
        $locationId = isset($data['location_id']) ? (int) $data['location_id'] : null;

        return [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [$lng, $lat],
            ],
            'properties' => [
                'id' => $applicationId,
                'location_id' => $locationId,
                'location' => $address,
                'street_no' => $data['street_no'] ?? null,
                'street' => $data['street'] ?? null,
                'suburb' => $data['suburb'] ?? null,
                'state' => $data['state'] ?? null,
                'post_code' => $data['post_code'] ?? null,
                'submitted' => $data['submitted'] ?? null,
                'created_at' => self::isoTimestamp($data['created_at'] ?? null),
                'search_id' => isset($data['search_id']) ? (int) $data['search_id'] : null,
                'search_name' => $data['search_name'] ?? null,
                'type' => $data['type'] ?? null,
                'portal_no' => $data['portal_no'] ?? null,
                'authority_no' => $data['authority_no'] ?? null,
                'description' => $data['description'] ?? null,
                'decision' => $data['decision'] ?? null,
                'estimated_cost' => $data['estimated_cost'] ?? null,
                'development_classes' => self::normalizeDevelopmentClasses($data['development_classes'] ?? []),
                'decision_classes' => self::normalizeDecisionClasses($data['decision_classes'] ?? []),
            ],
        ];
    }

    /**
     * @return list<array{id: ?int, development_class: mixed, name: mixed, description: mixed, icon: mixed, icon_priority: int}>
     */
    private static function normalizeDevelopmentClasses(mixed $classes): array
    {
        if (! is_array($classes)) {
            return [];
        }

        return array_values(array_map(
            static function (mixed $class): array {
                $item = is_array($class) ? $class : (array) $class;

                return [
                    'id' => isset($item['id']) ? (int) $item['id'] : null,
                    'development_class' => $item['development_class'] ?? null,
                    'name' => $item['name'] ?? null,
                    'description' => $item['description'] ?? null,
                    'icon' => $item['icon'] ?? null,
                    'icon_priority' => (int) ($item['icon_priority'] ?? 0),
                ];
            },
            $classes,
        ));
    }

    /**
     * @return list<array{id: ?int, name: mixed, description: mixed}>
     */
    private static function normalizeDecisionClasses(mixed $classes): array
    {
        if (! is_array($classes)) {
            return [];
        }

        return array_values(array_map(
            static function (mixed $class): array {
                $item = is_array($class) ? $class : (array) $class;

                return [
                    'id' => isset($item['id']) ? (int) $item['id'] : null,
                    'name' => $item['name'] ?? null,
                    'description' => $item['description'] ?? null,
                ];
            },
            $classes,
        ));
    }

    private static function isoTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::parse($value)->toIso8601String();
        }

        try {
            return Carbon::parse((string) $value)->toIso8601String();
        } catch (\Throwable) {
            return (string) $value;
        }
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
