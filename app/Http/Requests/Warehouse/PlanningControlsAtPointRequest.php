<?php

namespace App\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class PlanningControlsAtPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $layers = $this->input('layers', $this->input('layer'));
        if (is_string($layers) && $layers !== '') {
            $this->merge([
                'layers' => array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $layers) ?: []))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'layers' => ['sometimes', 'array'],
            'layers.*' => ['string', 'max:32'],
            'layer' => ['nullable', 'string', 'max:32'],
            'code' => ['nullable', 'string', 'max:64'],
            'include_geometry' => ['sometimes', 'boolean'],
            'geometry' => ['sometimes', 'boolean'],
            'include' => ['nullable', 'string', 'max:32'],
            'include_payload' => ['sometimes', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ];
    }

    /**
     * @return list<string>|null
     */
    public function layers(): ?array
    {
        if ($this->filled('layers') && is_array($this->input('layers'))) {
            return array_values(array_filter(array_map('strval', $this->input('layers'))));
        }

        if ($this->filled('layer')) {
            return [(string) $this->input('layer')];
        }

        return null;
    }
}
