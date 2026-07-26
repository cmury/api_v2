<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class ListApplicationsRequest extends FormRequest
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
            'state' => ['nullable', 'string', 'max:8'],
            'authority_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'filter' => ['nullable', 'string', 'max:255'],
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
            'estimated_cost_min' => ['nullable', 'numeric'],
            'estimated_cost_max' => ['nullable', 'numeric'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('imby.list_max_per_page', 100)],
            'page' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'string', 'max:64'],
        ];
    }
}
