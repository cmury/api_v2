<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class ListFacilitiesRequest extends FormRequest
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
            'facility_type' => ['nullable', 'string', 'max:32'],
            'operational_status' => ['nullable', 'string', 'max:32'],
            /** Map viewport: swLat,swLng,neLat,neLng */
            'bounds' => ['nullable', 'string', 'max:128'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('imby.list_max_per_page', 100)],
            'page' => ['nullable', 'integer', 'min:1'],
            'order' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return list<float>|null  [swLat, swLng, neLat, neLng]
     */
    public function bounds(): ?array
    {
        $raw = $this->input('bounds');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $parts = array_map('floatval', array_map('trim', explode(',', $raw)));
        if (count($parts) !== 4) {
            return null;
        }

        [$a, $b, $c, $d] = $parts;
        // Accept SW→NE or legacy NE→SW (first lat > third lat).
        if ($a <= $c) {
            return [$a, $b, $c, $d];
        }

        return [$c, $d, $a, $b];
    }
}
