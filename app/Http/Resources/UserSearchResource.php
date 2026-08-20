<?php

namespace App\Http\Resources;

use App\Models\UserSearch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserSearch */
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
            'last_notified_at' => $this->last_notified_at,
            'pinned' => (bool) $this->pinned,
            'category' => $this->category,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
