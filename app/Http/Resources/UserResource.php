<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'surname' => $this->surname,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'company' => $this->company,
            'is_verified' => (bool) $this->is_verified,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
