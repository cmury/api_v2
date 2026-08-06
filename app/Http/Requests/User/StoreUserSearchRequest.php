<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserSearchRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:64'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['required', 'integer', 'min:0', 'max:100000'],
            'filter' => ['nullable', 'array'],
            'notify' => ['required', 'boolean'],
            'pinned' => ['sometimes', 'boolean'],
            'category' => ['nullable', 'string', Rule::in(self::categories())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function categories(): array
    {
        return ['home', 'work', 'holiday_rental', 'project'];
    }
}
