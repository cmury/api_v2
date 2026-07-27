<?php

namespace App\Http\Requests\Warehouse;

use App\Support\Warehouse\ForecastQuery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForecastRequest extends FormRequest
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
            'metric' => ['nullable', 'string', Rule::in(ForecastQuery::METRICS)],
            'group_by' => ['nullable', 'string', Rule::in(ForecastQuery::GROUP_BY)],
            'horizon' => ['nullable', 'integer', 'min:1', 'max:24'],
            'horizon_months' => ['nullable', 'integer', 'min:1', 'max:24'],
            'history_months' => ['nullable', 'integer', 'min:6', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'state' => ['nullable', 'string', 'max:8'],
            'suburb' => ['nullable', 'string', 'max:128'],
            'authority_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'bounds' => ['sometimes', 'array', 'size:4'],
            'bounds.*' => ['numeric'],
            'map' => ['sometimes', 'array'],
            'map.bounds' => ['sometimes', 'array', 'size:4'],
            'application_class_ids' => ['sometimes', 'array'],
            'application_class_ids.*' => ['integer'],
            'development_class_ids' => ['sometimes', 'array'],
            'development_class_ids.*' => ['integer'],
            'decision_class_ids' => ['sometimes', 'array'],
            'decision_class_ids.*' => ['integer'],
            'legislation_ids' => ['sometimes', 'array'],
            'legislation_ids.*' => ['integer'],
            'legislation_id' => ['nullable', 'integer'],
        ];
    }
}
