<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class ListPlanningControlsRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'filter' => ['nullable', 'string', 'max:255'],
            'layer' => ['nullable', 'string', 'max:32'],
            'code' => ['nullable', 'string', 'max:64'],
            'epi_type' => ['nullable', 'string', 'max:8'],
            'lga_name' => ['nullable', 'string', 'max:80'],
            'authority_id' => ['nullable', 'integer', 'min:1'],
            'source' => ['nullable', 'string', 'max:32'],
            'bounds' => ['sometimes', 'array', 'size:4'],
            'bounds.*' => ['numeric'],
            'include_geometry' => ['sometimes', 'boolean'],
            'geometry' => ['sometimes', 'boolean'],
            'include' => ['nullable', 'string', 'max:32'],
            'include_payload' => ['sometimes', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('imby.list_max_per_page', 100)],
            'page' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'string', 'max:64'],
        ];
    }
}
