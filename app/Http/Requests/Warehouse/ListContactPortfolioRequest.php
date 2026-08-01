<?php

namespace App\Http\Requests\Warehouse;

use App\Models\ApplicationContact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListContactPortfolioRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::in(ApplicationContact::STATUSES)],
            'role' => ['nullable', 'string', Rule::in(ApplicationContact::ROLES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('imby.list_max_per_page', 100)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
