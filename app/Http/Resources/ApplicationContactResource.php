<?php

namespace App\Http\Resources;

use App\Models\ApplicationContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApplicationContact */
class ApplicationContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'contact_id' => $this->contact_id,
            'role' => $this->role,
            'is_primary' => (bool) $this->is_primary,
            'source' => $this->source,
            'status' => $this->status,
            'email_override' => $this->email_override,
            'phone_override' => $this->phone_override,
            'notes' => $this->notes,
            'contributed_by_user_id' => $this->contributed_by_user_id,
            'contact' => ContactResource::make($this->whenLoaded('contact')),
            'application' => ApplicationResource::make($this->whenLoaded('application')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
