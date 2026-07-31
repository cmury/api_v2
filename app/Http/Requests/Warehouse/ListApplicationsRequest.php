<?php

namespace App\Http\Requests\Warehouse;

use App\Http\Requests\Warehouse\Concerns\ValidatesTaxonomyFilters;
use Illuminate\Foundation\Http\FormRequest;

class ListApplicationsRequest extends FormRequest
{
    use ValidatesTaxonomyFilters;

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
            'suburb' => ['nullable', 'string', 'max:128'],
            'search' => ['nullable', 'string', 'max:255'],
            'filter' => ['nullable', 'string', 'max:255'],
            'submitted_from' => ['nullable', 'date'],
            'submitted_to' => ['nullable', 'date'],
            'date' => ['sometimes', 'array'],
            'date.type' => ['nullable', 'string'],
            'date.start' => ['nullable', 'date'],
            'date.end' => ['nullable', 'date'],
            'source' => ['nullable', 'string', 'max:32'],
            ...$this->taxonomyFilterRules(),
            'estimated_cost_min' => ['nullable', 'numeric'],
            'estimated_cost_max' => ['nullable', 'numeric'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('imby.list_max_per_page', 100)],
            'page' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'string', 'max:64'],
        ];
    }
}
