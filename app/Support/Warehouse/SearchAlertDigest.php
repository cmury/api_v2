<?php

namespace App\Support\Warehouse;

/**
 * Groups notification GeoJSON into a per-search digest for email.
 */
final class SearchAlertDigest
{
    /**
     * @param  array{features?: list<array<string, mixed>>}  $geojson
     * @return list<array{id: int, name: string, applications: list<array<string, mixed>>, total: int, omitted: int}>
     */
    public function fromGeoJson(array $geojson, ?int $limit = null): array
    {
        $limit ??= max(1, (int) config('imby.search_alerts.per_search_limit', 20));
        $features = $geojson['features'] ?? [];
        $bySearch = [];

        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }

            $properties = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $searchId = (int) ($properties['search_id'] ?? 0);
            if ($searchId <= 0) {
                continue;
            }

            if (! isset($bySearch[$searchId])) {
                $bySearch[$searchId] = [
                    'id' => $searchId,
                    'name' => is_string($properties['search_name'] ?? null) && $properties['search_name'] !== ''
                        ? $properties['search_name']
                        : 'Saved search',
                    'applications' => [],
                    'total' => 0,
                    'omitted' => 0,
                ];
            }

            $bySearch[$searchId]['total']++;

            if (count($bySearch[$searchId]['applications']) < $limit) {
                $bySearch[$searchId]['applications'][] = $this->application($properties);
            }
        }

        foreach ($bySearch as &$search) {
            $search['omitted'] = max(0, $search['total'] - count($search['applications']));
        }
        unset($search);

        return array_values($bySearch);
    }

    public function totalApplications(array $searches): int
    {
        return array_sum(array_map(
            static fn (array $search): int => (int) ($search['total'] ?? 0),
            $searches,
        ));
    }

    public function applicationUrl(int $applicationId): string
    {
        $base = rtrim((string) config('imby.search_alerts.frontend_url', 'http://localhost:5174'), '/');

        return $base.'/applications/'.$applicationId;
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    private function application(array $properties): array
    {
        $id = (int) ($properties['id'] ?? 0);
        $location = $this->stringOrNull($properties['location'] ?? null)
            ?? $this->stringOrNull($properties['suburb'] ?? null)
            ?? ($id > 0 ? 'Application '.$id : 'Application');

        $parts = array_values(array_filter([
            $this->stringOrNull($properties['type'] ?? null),
            $this->stringOrNull($properties['decision'] ?? null),
            $this->formatCost($properties['estimated_cost'] ?? null),
            $this->stringOrNull($properties['portal_no'] ?? null)
                ?? $this->stringOrNull($properties['authority_no'] ?? null),
        ]));

        return [
            'id' => $id,
            'location' => $location,
            'headline' => $parts === [] ? $location : $location.' — '.implode(' — ', $parts),
            'type' => $this->stringOrNull($properties['type'] ?? null),
            'decision' => $this->stringOrNull($properties['decision'] ?? null),
            'estimated_cost' => $this->formatCost($properties['estimated_cost'] ?? null),
            'portal_no' => $this->stringOrNull($properties['portal_no'] ?? null),
            'authority_no' => $this->stringOrNull($properties['authority_no'] ?? null),
            'submitted' => $this->stringOrNull($properties['submitted'] ?? null),
            'url' => $id > 0 ? $this->applicationUrl($id) : null,
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function formatCost(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        return '$'.number_format((float) $value, 0);
    }
}
