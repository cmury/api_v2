<?php

namespace App\Http\Requests\Geocode;

use Illuminate\Foundation\Http\FormRequest;

class GeocodeSearchRequest extends FormRequest
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
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ];
    }
}
