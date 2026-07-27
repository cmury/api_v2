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
        $metrics = $this->is('api/charts') || $this->is('charts')
            ? StatsQuery::CHART_METRICS
            : StatsQuery::METRICS;

        return [
            'metric' => ['required', 'string', Rule::in($metrics)],
            'scope' => ['nullable', 'string', Rule::in(['all', 'state', 'authority', 'location', 'map'])],
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
            'application_class_ids' => ['sometimes', 'array'],
            'application_class_ids.*' => ['integer'],
            'development_class_ids' => ['sometimes', 'array'],
            'development_class_ids.*' => ['integer'],
            'decision_class_ids' => ['sometimes', 'array'],
            'decision_class_ids.*' => ['integer'],
            'legislation_ids' => ['sometimes', 'array'],
            'legislation_ids.*' => ['integer'],
            'legislation_id' => ['nullable', 'integer'],
            'interval' => ['nullable', 'string', Rule::in(['day', 'week', 'month', 'year'])],
            'format' => ['nullable', 'string', Rule::in(['auto', 'timeseries', 'calendar', 'categorical', 'bands'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
