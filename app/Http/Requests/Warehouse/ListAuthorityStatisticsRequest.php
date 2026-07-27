<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class ListAuthorityStatisticsRequest extends FormRequest
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
            'statistics_code' => ['nullable', 'integer'],
            'authority_id' => ['nullable', 'integer'],
            'state' => ['nullable', 'string', 'max:8'],
            'measure' => ['nullable', 'string', 'max:128'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'source' => ['nullable', 'string', 'max:64'],
            'latest' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('imby.list_max_per_page', 100)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
