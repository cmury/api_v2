<?php

namespace App\Http\Requests\Warehouse;

use App\Models\ApplicationContact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClaimApplicationRequest extends FormRequest
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
            'role' => ['required', 'string', Rule::in(ApplicationContact::ROLES)],
            'is_primary' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Optional profile bootstrap when the user has no linked contact yet.
            'type' => ['sometimes', 'string', Rule::in(['person', 'organisation'])],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'website' => ['nullable', 'string', 'max:255'],
            'abn' => ['nullable', 'string', 'max:14'],
        ];
    }
}
