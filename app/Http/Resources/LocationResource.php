<?php

namespace App\Http\Resources;

use App\Models\Location;
use App\Support\Warehouse\GeoJson;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Location */
class LocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'formatted_address' => GeoJson::stripCountry($this->formatted_address),
            'street_no' => $this->street_no,
            'street' => $this->street,
            'suburb' => $this->suburb,
            'state' => $this->state,
            'post_code' => $this->post_code,
            'country' => $this->country,
            'location_raw' => $this->location_raw,
            'parcel' => $this->parcel,
            'lat' => $this->when(isset($this->lat), $this->lat),
            'lng' => $this->when(isset($this->lng), $this->lng),
            'applications_count' => $this->whenCounted('applications'),
            'applications' => ApplicationResource::collection($this->whenLoaded('applications')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
