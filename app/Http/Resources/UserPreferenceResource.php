<?php

namespace App\Http\Resources;

use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserPreference */
class UserPreferenceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'map_type' => $this->map_type,
            'default_search_id' => $this->default_search_id,
            'date_range' => $this->date_range,
            'notification_frequency' => $this->notification_frequency,
            'locale' => $this->locale,
            'updated_at' => $this->updated_at,
        ];
    }
}
