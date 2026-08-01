<?php

namespace App\Http\Requests\Warehouse;

use App\Models\ApplicationContact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApplicationContactRequest extends FormRequest
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
            'contact_id' => ['nullable', 'integer', 'min:1', 'required_without:name'],
            'type' => ['nullable', 'string', Rule::in(['person', 'organisation'])],
            'name' => ['nullable', 'string', 'max:255', 'required_without:contact_id'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'mobile' => ['nullable', 'string', 'max:32'],
            'website' => ['nullable', 'string', 'max:255'],
            'abn' => ['nullable', 'string', 'max:14'],
            'role' => ['required', 'string', Rule::in(ApplicationContact::ROLES)],
            'is_primary' => ['sometimes', 'boolean'],
            'email_override' => ['nullable', 'email', 'max:255'],
            'phone_override' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
