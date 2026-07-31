<?php

namespace App\Http\Resources;

use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Facility */
class FacilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'source_id' => $this->source_id,
            'facility_type' => $this->facility_type,
            'name' => $this->name,
            'name_alt' => $this->name_alt,
            'operational_status' => $this->operational_status,
            'state' => $this->state,
            'lat' => $this->when(isset($this->lat), fn () => (float) $this->lat),
            'lng' => $this->when(isset($this->lng), fn () => (float) $this->lng),
            'source_modified_at' => $this->source_modified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
