<?php

namespace App\Http\Resources;

use App\Models\Authority;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Authority */
class AuthorityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'region' => $this->region,
            'state' => $this->state,
            'country' => $this->country,
            'amalgamated' => $this->amalgamated,
            'statistics_code' => $this->statistics_code,
            'tracking' => (bool) $this->tracking,
            'tracking_system' => $this->tracking_system,
            'tracking_url' => $this->tracking_url,
            'phone' => $this->phone,
            'email' => $this->email,
            'url' => $this->url,
            'postal_address' => $this->postal_address,
            'postal_suburb' => $this->postal_suburb,
            'postal_code' => $this->postal_code,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'applications_count' => $this->when(isset($this->applications_count), $this->applications_count),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
