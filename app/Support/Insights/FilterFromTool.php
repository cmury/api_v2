<?php

namespace App\Support\Insights;

use App\Support\Warehouse\ApplicationFilter;
use Laravel\Ai\Tools\Request;

/**
 * Maps Insights tool arguments onto the shared warehouse ApplicationFilter.
 */
final class FilterFromTool
{
    public static function make(Request $request): ApplicationFilter
    {
        $input = array_filter([
            'state' => self::string($request, 'state'),
            'suburb' => self::string($request, 'suburb'),
            'authority_id' => self::int($request, 'authority_id'),
            'location_id' => self::int($request, 'location_id'),
            'search' => self::string($request, 'search'),
            'submitted_from' => self::string($request, 'submitted_from'),
            'submitted_to' => self::string($request, 'submitted_to'),
            'estimated_cost_min' => self::float($request, 'estimated_cost_min'),
            'estimated_cost_max' => self::float($request, 'estimated_cost_max'),
            'application_class_ids' => self::idList($request, 'application_class_ids'),
            'development_class_ids' => self::idList($request, 'development_class_ids'),
            'decision_class_ids' => self::idList($request, 'decision_class_ids'),
            'legislation_ids' => self::idList($request, 'legislation_ids'),
            'amalgamated' => $request->boolean('include_amalgamated'),
        ], static fn ($v) => $v !== null && $v !== [] && $v !== '');

        return ApplicationFilter::fromArray($input);
    }

    private static function string(Request $request, string $key): ?string
    {
        $value = $request[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    private static function int(Request $request, string $key): ?int
    {
        $value = $request[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function float(Request $request, string $key): ?float
    {
        $value = $request[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    /**
     * @return list<int>|null
     */
    private static function idList(Request $request, string $key): ?array
    {
        $value = $request[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = preg_split('/\s*,\s*/', $value) ?: [];
        }

        if (! is_array($value)) {
            return [(int) $value];
        }

        $ids = array_values(array_filter(array_map('intval', $value), static fn (int $id) => $id > 0));

        return $ids === [] ? null : $ids;
    }
}
