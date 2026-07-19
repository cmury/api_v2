<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\UserPreference */
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
            'new_application_email_frequency' => $this->new_application_email_frequency,
            'locale' => $this->locale,
            'updated_at' => $this->updated_at,
        ];
    }
}
