<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
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
            'map_type' => ['sometimes', 'nullable', 'string', Rule::in(config('imby.map_types'))],
            'date_range' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:120'],
            'new_application_email_frequency' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(config('imby.email_frequencies')),
            ],
            'locale' => ['sometimes', 'nullable', 'string', 'max:8'],
            'default_search_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users_searches', 'id')->where(
                    fn ($query) => $query->where('user_id', $this->user()->id)
                ),
            ],
        ];
    }
}
