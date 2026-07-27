<?php

namespace App\Http\Requests\Map;

use Illuminate\Foundation\Http\FormRequest;

class MapMarkersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $query = $this->input('query');

        if (is_string($query) && $query !== '') {
            $decoded = json_decode($query, true);
            if (is_array($decoded)) {
                $this->merge($decoded);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'app' => ['sometimes', 'array'],
            'app.*' => ['integer'],
            'type' => ['sometimes', 'array'],
            'type.*' => ['integer'],
            'status' => ['sometimes', 'array'],
            'status.*' => ['integer'],
            'application_class_ids' => ['sometimes', 'array'],
            'application_class_ids.*' => ['integer'],
            'development_class_ids' => ['sometimes', 'array'],
            'development_class_ids.*' => ['integer'],
            'decision_class_ids' => ['sometimes', 'array'],
            'decision_class_ids.*' => ['integer'],
            'legislation_ids' => ['sometimes', 'array'],
            'legislation_ids.*' => ['integer'],
            'legislation_id' => ['nullable', 'integer'],
            'estvalue' => ['sometimes', 'array'],
            'estvalue.low' => ['nullable', 'numeric'],
            'estvalue.high' => ['nullable', 'numeric'],
            'estimated_cost_min' => ['nullable', 'numeric'],
            'estimated_cost_max' => ['nullable', 'numeric'],
            'date' => ['sometimes', 'array'],
            'date.type' => ['nullable', 'string'],
            'date.start' => ['nullable', 'date'],
            'date.end' => ['nullable', 'date'],
            'map' => ['sometimes', 'array'],
            'map.bounds' => ['required_with:map', 'array', 'size:4'],
            'map.bounds.*' => ['numeric'],
            'map.center' => ['sometimes', 'array'],
            'map.zoom' => ['nullable', 'numeric'],
            'bounds' => ['sometimes', 'array', 'size:4'],
            'bounds.*' => ['numeric'],
            'state' => ['nullable', 'string', 'max:8'],
            'authority_id' => ['nullable', 'integer'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filterPayload(): array
    {
        return $this->safe()->all();
    }
}
