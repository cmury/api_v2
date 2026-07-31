<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class ListFacilitiesRequest extends FormRequest
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
            'state' => ['nullable', 'string', 'max:8'],
            'facility_type' => ['nullable', 'string', 'max:32'],
            'operational_status' => ['nullable', 'string', 'max:32'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('imby.list_max_per_page', 100)],
            'page' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'string', 'max:64'],
        ];
    }
}
