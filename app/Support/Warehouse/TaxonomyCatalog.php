<?php

namespace App\Support\Warehouse;

use App\Models\ApplicationClass;
use App\Models\ApplicationType;
use App\Models\DecisionClass;
use App\Models\DecisionType;
use App\Models\DevelopmentClass;
use App\Models\DevelopmentType;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * Shared taxonomy lists used by GET /api/taxonomies/* and list_taxonomies tool.
 */
final class TaxonomyCatalog
{
    public const KINDS = [
        'application_classes', 'application_types',
        'development_classes', 'development_types',
        'decision_classes', 'decision_types',
    ];

    /**
     * @return list<array<string, mixed>>
     */
    public function list(
        string $kind,
        ?string $jurisdiction = null,
        ?int $classId = null,
        ?string $search = null,
        ?int $limit = 100,
    ): array {
        $kind = strtolower($kind);
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException('kind must be one of: '.implode(', ', self::KINDS));
        }

        return match ($kind) {
            'application_classes' => $this->classes(ApplicationClass::query(), $jurisdiction, $search, $limit, [
                'id', 'name', 'display_name', 'abbrev', 'jurisdiction', 'icon',
            ]),
            'decision_classes' => $this->classes(DecisionClass::query(), $jurisdiction, $search, $limit, [
                'id', 'name', 'display_name', 'abbrev', 'jurisdiction', 'icon',
            ]),
            'development_classes' => $this->developmentClasses($jurisdiction, $search, $limit),
            'application_types' => $this->types(
                ApplicationType::query(), 'application_class_id', $jurisdiction, $classId, $search, $limit,
                ['id', 'name', 'display_name', 'application_class_id', 'jurisdiction'],
            ),
            'development_types' => $this->types(
                DevelopmentType::query(), 'development_class_id', $jurisdiction, $classId, $search, $limit,
                ['id', 'name', 'display_name', 'development_class_id', 'jurisdiction'],
            ),
            'decision_types' => $this->types(
                DecisionType::query(), 'decision_class_id', $jurisdiction, $classId, $search, $limit,
                ['id', 'name', 'display_name', 'decision_class_id', 'jurisdiction'],
            ),
        };
    }

    /**
     * @param  list<string>  $columns
     * @return list<array<string, mixed>>
     */
    private function classes(Builder $query, ?string $jurisdiction, ?string $search, int $limit, array $columns): array
    {
        if ($jurisdiction) {
            $query->where('jurisdiction', strtoupper($jurisdiction));
        }
        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('display_name', 'ilike', '%'.$search.'%');
            });
        }

        return $query->orderBy('name')->limit($limit)->get($columns)->toArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function developmentClasses(?string $jurisdiction, ?string $search, int $limit): array
    {
        $query = DevelopmentClass::query();
        if ($jurisdiction) {
            $query->where('jurisdiction', strtoupper($jurisdiction));
        }
        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('display_name', 'ilike', '%'.$search.'%')
                    ->orWhere('development_class', 'ilike', '%'.$search.'%');
            });
        }

        return $query->orderBy('name')->limit($limit)
            ->get(['id', 'name', 'display_name', 'abbrev', 'development_class', 'jurisdiction', 'icon'])
            ->toArray();
    }

    /**
     * @param  list<string>  $columns
     * @return list<array<string, mixed>>
     */
    private function types(
        Builder $query,
        string $classFk,
        ?string $jurisdiction,
        ?int $classId,
        ?string $search,
        int $limit,
        array $columns,
    ): array {
        if ($jurisdiction) {
            $query->where('jurisdiction', strtoupper($jurisdiction));
        }
        if ($classId) {
            $query->where($classFk, $classId);
        }
        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('display_name', 'ilike', '%'.$search.'%');
            });
        }

        return $query->orderBy('name')->limit($limit)->get($columns)->toArray();
    }
}
