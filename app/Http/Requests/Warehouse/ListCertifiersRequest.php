<?php

namespace App\Http\Requests\Warehouse;

use App\Models\Certifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCertifiersRequest extends FormRequest
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
            'status' => ['nullable', 'string', 'max:32'],
            'registration_type' => ['nullable', 'string', Rule::in(Certifier::REGISTRATION_TYPES)],
            'registration_number' => ['nullable', 'string', 'max:64'],
            'suburb' => ['nullable', 'string', 'max:128'],
            'postcode' => ['nullable', 'string', 'max:16'],
            'enrichment_status' => ['nullable', 'string', Rule::in(Certifier::ENRICHMENT_STATUSES)],
            'enriched' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('imby.list_max_per_page', 100)],
            'page' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'string', 'max:64'],
        ];
    }
}
