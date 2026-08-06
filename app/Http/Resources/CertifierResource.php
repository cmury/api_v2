<?php

namespace App\Http\Resources;

use App\Models\Certifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Certifier */
class CertifierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'state' => $this->state,
            'registration_number' => $this->registration_number,
            'registration_type' => $this->registration_type,
            'name' => $this->name,
            'organisation' => $this->organisation,
            'status' => $this->status,
            'classes' => $this->classes,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'website' => $this->website,
            'address_line1' => $this->address_line1,
            'suburb' => $this->suburb,
            'state_code' => $this->state_code,
            'postcode' => $this->postcode,
            'registered_at' => $this->registered_at,
            'ceased_at' => $this->ceased_at,
            'source' => $this->source,
            'enrichment_status' => $this->enrichment_status,
            'enriched_at' => $this->enriched_at,
            'applications_count' => $this->when(
                isset($this->applications_count),
                fn () => (int) $this->applications_count,
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
