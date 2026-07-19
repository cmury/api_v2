<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\UserSearch */
class UserSearchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'radius' => $this->radius,
            'filter' => $this->filter ?? [],
            'notify' => (bool) $this->notify,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
