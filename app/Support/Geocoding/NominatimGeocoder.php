<?php

namespace App\Support\Geocoding;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class NominatimGeocoder
{
    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min($limit, 10));
        $cacheKey = sprintf('geocode:v1:search:%s:%d', mb_strtolower($query), $limit);

        return Cache::remember(
            $cacheKey,
            now()->addSeconds((int) config('imby.geocode_cache_ttl', 604800)),
            fn () => $this->fetchSearch($query, $limit),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function reverse(float $lat, float $lng): ?array
    {
        $roundedLat = round($lat, 5);
        $roundedLng = round($lng, 5);
        $cacheKey = sprintf('geocode:v1:reverse:%s:%s', $roundedLat, $roundedLng);

        return Cache::remember(
            $cacheKey,
            now()->addSeconds((int) config('imby.geocode_cache_ttl', 604800)),
            fn () => $this->fetchReverse($roundedLat, $roundedLng),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchSearch(string $query, int $limit): array
    {
        $response = Http::withHeaders($this->headers())
            ->timeout((int) config('imby.geocode_timeout', 8))
            ->acceptJson()
            ->get($this->baseUrl().'/search', [
                'format' => 'json',
                'q' => $query,
                'countrycodes' => config('imby.geocode_countrycodes', 'au'),
                'addressdetails' => 1,
                'limit' => $limit,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Geocode provider request failed: '.$response->status());
        }

        $rows = $response->json();
        if (! is_array($rows)) {
            return [];
        }

        $results = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $mapped = $this->mapResult($row);
            if ($mapped !== null) {
                $results[] = $mapped;
            }
        }

        return $results;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchReverse(float $lat, float $lng): ?array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout((int) config('imby.geocode_timeout', 8))
                ->acceptJson()
                ->get($this->baseUrl().'/reverse', [
                    'format' => 'json',
                    'lat' => $lat,
                    'lon' => $lng,
                    'addressdetails' => 1,
                    'zoom' => 16,
                ]);
        } catch (RequestException $e) {
            throw new RuntimeException('Geocode provider request failed', 0, $e);
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new RuntimeException('Geocode provider request failed: '.$response->status());
        }

        $row = $response->json();
        if (! is_array($row) || isset($row['error'])) {
            return null;
        }

        return $this->mapResult($row);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function mapResult(array $row): ?array
    {
        $lat = isset($row['lat']) ? (float) $row['lat'] : null;
        $lng = isset($row['lon']) ? (float) $row['lon'] : null;
        if ($lat === null || $lng === null || ! is_finite($lat) || ! is_finite($lng)) {
            return null;
        }

        $address = is_array($row['address'] ?? null) ? $row['address'] : [];
        $suburb = $this->firstString($address, [
            'suburb',
            'neighbourhood',
            'quarter',
            'city_district',
            'town',
            'village',
            'hamlet',
            'locality',
        ]);
        $lga = $this->firstString($address, [
            'municipality',
            'city',
            'county',
            'state_district',
        ]);
        $state = $this->firstString($address, ['state']);
        $postcode = $this->firstString($address, ['postcode']);

        $labelParts = array_values(array_filter([$suburb, $lga, $state], fn ($part) => is_string($part) && $part !== ''));
        $label = $labelParts !== []
            ? implode(', ', $labelParts)
            : (is_string($row['display_name'] ?? null) ? (string) $row['display_name'] : null);

        if ($label === null || $label === '') {
            $label = sprintf('%.5f, %.5f', $lat, $lng);
        }

        $bbox = null;
        if (isset($row['boundingbox']) && is_array($row['boundingbox']) && count($row['boundingbox']) === 4) {
            // Nominatim: [south, north, west, east] → SW→NE [swLat, swLng, neLat, neLng]
            $bbox = [
                (float) $row['boundingbox'][0],
                (float) $row['boundingbox'][2],
                (float) $row['boundingbox'][1],
                (float) $row['boundingbox'][3],
            ];
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'label' => $label,
            'display_name' => is_string($row['display_name'] ?? null)
                ? (string) $row['display_name']
                : $label,
            'suburb' => $suburb,
            'lga' => $lga,
            'state' => $state,
            'postcode' => $postcode,
            'bbox' => $bbox,
            'source' => 'nominatim',
        ];
    }

    /**
     * @param  array<string, mixed>  $address
     * @param  list<string>  $keys
     */
    private function firstString(array $address, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $address[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'User-Agent' => (string) config(
                'imby.geocode_user_agent',
                'IMBY/2.0 (https://imby.com.au; geocode@imby.com.au)',
            ),
        ];
    }

    private function baseUrl(): string
    {
        return rtrim((string) config(
            'imby.geocode_base_url',
            'https://nominatim.openstreetmap.org',
        ), '/');
    }
}
