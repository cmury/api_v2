<?php

namespace App\Http\Requests\Warehouse\Concerns;

/**
 * Shared taxonomy filter validation for warehouse list/stats/map requests.
 */
trait ValidatesTaxonomyFilters
{
    /**
     * @return array<string, mixed>
     */
    protected function taxonomyFilterRules(): array
    {
        return [
            'application_class_ids' => ['sometimes', 'array'],
            'application_class_ids.*' => ['integer'],
            'development_class_ids' => ['sometimes', 'array'],
            'development_class_ids.*' => ['integer'],
            'decision_class_ids' => ['sometimes', 'array'],
            'decision_class_ids.*' => ['integer'],
            'application_type_ids' => ['sometimes', 'array'],
            'application_type_ids.*' => ['integer'],
            'development_type_ids' => ['sometimes', 'array'],
            'development_type_ids.*' => ['integer'],
            'decision_type_ids' => ['sometimes', 'array'],
            'decision_type_ids.*' => ['integer'],
            'legislation_ids' => ['sometimes', 'array'],
            'legislation_ids.*' => ['integer'],
            'legislation_id' => ['nullable', 'integer'],
        ];
    }
}
