<?php

namespace App\Http\Requests\Warehouse;

use App\Support\Warehouse\StatsQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'metric' => ['required', 'string', Rule::in(StatsQuery::METRICS)],
            ...$this->filterRules(),
        ];
    }

    /**
     * Shared warehouse filters for stats and charts.
     *
     * @return array<string, mixed>
     */
    protected function filterRules(): array
    {
        return [
            'state' => ['nullable', 'string', 'max:8'],
            'authority_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'bounds' => ['sometimes', 'array', 'size:4'],
            'bounds.*' => ['numeric'],
            'map' => ['sometimes', 'array'],
            'map.bounds' => ['sometimes', 'array', 'size:4'],
            'submitted_from' => ['nullable', 'date'],
            'submitted_to' => ['nullable', 'date'],
            'date' => ['sometimes', 'array'],
            'date.type' => ['nullable', 'string'],
            'date.start' => ['nullable', 'date'],
            'date.end' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:32'],
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
            'suburb' => ['nullable', 'string', 'max:128'],
            'estimated_cost_min' => ['nullable', 'numeric'],
            'estimated_cost_max' => ['nullable', 'numeric'],
        ];
    }
}
