<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class ListLocationsRequest extends FormRequest
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
            'suburb' => ['nullable', 'string', 'max:128'],
            'authority_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:255'],
            'filter' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('imby.list_max_per_page', 100)],
            'page' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'string', 'max:64'],
        ];
    }
}
