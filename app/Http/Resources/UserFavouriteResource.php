<?php

namespace App\Http\Resources;

use App\Models\UserFavourite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserFavourite */
class UserFavouriteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'notes' => $this->notes,
            'application' => ApplicationResource::make($this->whenLoaded('application')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
