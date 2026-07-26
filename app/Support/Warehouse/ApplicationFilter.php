<?php

namespace App\Support\Warehouse;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Shared warehouse filter for map, applications list, stats, and notifications.
 *
 * Accepts the old map `query` JSON keys (app, type, status, estvalue, date, map)
 * and clearer aliases (application_class_ids, development_class_ids, …).
 */
final class ApplicationFilter
{
    /**
     * @param  list<float>|null  $bounds  [latMax, lngMax, latMin, lngMin]
     * @param  list<int>|null  $applicationClassIds
     * @param  list<int>|null  $developmentClassIds
     * @param  list<int>|null  $decisionClassIds
     */
    public function __construct(
        public readonly ?array $bounds = null,
        public readonly ?array $applicationClassIds = null,
        public readonly ?array $developmentClassIds = null,
        public readonly ?array $decisionClassIds = null,
        public readonly ?float $estimatedCostMin = null,
        public readonly ?float $estimatedCostMax = null,
        public readonly ?CarbonInterface $submittedFrom = null,
        public readonly ?CarbonInterface $submittedTo = null,
        public readonly ?string $state = null,
        public readonly ?int $authorityId = null,
        public readonly ?int $locationId = null,
        public readonly ?string $search = null,
        public readonly bool $includeAmalgamated = false,
        public readonly ?float $centerLat = null,
        public readonly ?float $centerLng = null,
        public readonly ?int $radiusMeters = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input, bool $defaultDateWindow = false): self
    {
        $bounds = self::bounds($input);
        $center = self::center($input);

        [$from, $to] = DateRange::resolve(
            isset($input['date']) && is_array($input['date']) ? $input['date'] : null,
            $defaultDateWindow ? (int) config('imby.default_submitted_days', 365) : null,
        );

        // Flat submitted_from / submitted_to override date block when present.
        if (array_key_exists('submitted_from', $input) || array_key_exists('submitted_to', $input)) {
            $from = ! empty($input['submitted_from'])
                ? Carbon::parse($input['submitted_from'])->startOfDay()
                : null;
            $to = ! empty($input['submitted_to'])
                ? Carbon::parse($input['submitted_to'])->endOfDay()
                : null;
        }

        $est = is_array($input['estvalue'] ?? null) ? $input['estvalue'] : [];

        return new self(
            bounds: $bounds,
            applicationClassIds: self::idList($input['application_class_ids'] ?? $input['app'] ?? null),
            developmentClassIds: self::idList($input['development_class_ids'] ?? $input['type'] ?? null),
            decisionClassIds: self::idList($input['decision_class_ids'] ?? $input['status'] ?? null),
            estimatedCostMin: self::floatOrNull($input['estimated_cost_min'] ?? $est['low'] ?? null),
            estimatedCostMax: self::floatOrNull($input['estimated_cost_max'] ?? $est['high'] ?? null),
            submittedFrom: $from,
            submittedTo: $to,
            state: self::stringOrNull($input['state'] ?? null),
            authorityId: self::intOrNull($input['authority_id'] ?? null),
            locationId: self::intOrNull($input['location_id'] ?? null),
            search: self::stringOrNull($input['search'] ?? $input['filter'] ?? null),
            includeAmalgamated: filter_var($input['amalgamated'] ?? false, FILTER_VALIDATE_BOOLEAN),
            centerLat: $center['lat'],
            centerLng: $center['lng'],
            radiusMeters: self::intOrNull($input['radius'] ?? $input['radius_meters'] ?? null),
        );
    }

    /**
     * @return list<float>|null
     */
    private static function bounds(array $input): ?array
    {
        $raw = $input['map']['bounds'] ?? $input['bounds'] ?? null;

        if (! is_array($raw) || count($raw) !== 4) {
            return null;
        }

        return [
            (float) $raw[0],
            (float) $raw[1],
            (float) $raw[2],
            (float) $raw[3],
        ];
    }

    /**
     * @return array{lat: float|null, lng: float|null}
     */
    private static function center(array $input): array
    {
        $mapCenter = $input['map']['center'] ?? null;

        if (is_array($mapCenter) && count($mapCenter) >= 2) {
            return ['lat' => (float) $mapCenter[0], 'lng' => (float) $mapCenter[1]];
        }

        return [
            'lat' => self::floatOrNull($input['lat'] ?? null),
            'lng' => self::floatOrNull($input['lng'] ?? null),
        ];
    }

    /**
     * @return list<int>|null
     */
    private static function idList(mixed $value): ?array
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (! is_array($value)) {
            $value = [$value];
        }

        $ids = array_values(array_unique(array_map('intval', $value)));

        return $ids === [] ? null : $ids;
    }

    private static function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
