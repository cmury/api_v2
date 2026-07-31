<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class ApplicationsNearFacilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $routeFacility = $this->route('facility');
        if (! $this->filled('facility_id') && $routeFacility !== null) {
            $this->merge([
                'facility_id' => is_object($routeFacility) ? (int) $routeFacility->id : (int) $routeFacility,
            ]);
        }

        foreach ([
            'application_class_ids',
            'development_class_ids',
            'decision_class_ids',
            'application_type_ids',
            'development_type_ids',
            'decision_type_ids',
            'legislation_ids',
        ] as $key) {
            $value = $this->input($key);
            if (is_string($value) && $value !== '') {
                $this->merge([
                    $key => array_values(array_filter(array_map('intval', preg_split('/\s*,\s*/', $value) ?: []))),
                ]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'facility_id' => ['nullable', 'integer', 'min:1', 'required_without:facility_search'],
            'facility_search' => ['nullable', 'string', 'max:255', 'required_without:facility_id'],
            'facility_type' => ['nullable', 'string', 'max:32'],
            'radius' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'radius_meters' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'state' => ['nullable', 'string', 'max:8'],
            'authority_id' => ['nullable', 'integer'],
            'suburb' => ['nullable', 'string', 'max:128'],
            'search' => ['nullable', 'string', 'max:255'],
            'submitted_from' => ['nullable', 'date'],
            'submitted_to' => ['nullable', 'date'],
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
            'estimated_cost_min' => ['nullable', 'numeric'],
            'estimated_cost_max' => ['nullable', 'numeric'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('imby.list_max_per_page', 100)],
            'page' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * Filter payload for ApplicationFilter (excludes facility/radius controls).
     *
     * @return array<string, mixed>
     */
    public function applicationFilterInput(): array
    {
        return $this->safe()->only([
            'state',
            'authority_id',
            'suburb',
            'search',
            'submitted_from',
            'submitted_to',
            'source',
            'application_class_ids',
            'development_class_ids',
            'decision_class_ids',
            'application_type_ids',
            'development_type_ids',
            'decision_type_ids',
            'legislation_ids',
            'estimated_cost_min',
            'estimated_cost_max',
        ]);
    }

    public function radiusMeters(): int
    {
        return (int) ($this->input('radius') ?: $this->input('radius_meters') ?: 1000);
    }

    public function facilityId(): ?int
    {
        return $this->filled('facility_id') ? (int) $this->input('facility_id') : null;
    }

    public function facilitySearch(): ?string
    {
        return $this->filled('facility_search') ? (string) $this->input('facility_search') : null;
    }
}
