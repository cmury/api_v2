<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StartPropertyReportPaymentRequest extends FormRequest
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
            'location_id' => ['nullable', 'integer', 'min:1'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:500'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasLocation = $this->filled('location_id');
            $hasPoint = $this->filled('lat') && $this->filled('lng');
            $hasAddress = $this->filled('address');

            if (! $hasLocation && ! $hasPoint && ! $hasAddress) {
                $validator->errors()->add(
                    'location_id',
                    'Provide location_id, lat/lng, or address for the property report.',
                );
            }

            if ($this->filled('lat') xor $this->filled('lng')) {
                $validator->errors()->add('lat', 'Both lat and lng are required together.');
            }
        });
    }
}
