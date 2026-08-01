<?php

namespace App\Http\Requests\Warehouse;

use App\Models\ApplicationContact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListContactsRequest extends FormRequest
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
            'type' => ['nullable', 'string', Rule::in(['person', 'organisation'])],
            'email' => ['nullable', 'email', 'max:255'],
            'abn' => ['nullable', 'string', 'max:14'],
            'role' => ['nullable', 'string', Rule::in(ApplicationContact::ROLES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('imby.list_max_per_page', 100)],
            'page' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'string', 'max:64'],
        ];
    }
}
