<?php

namespace App\Http\Resources;

use App\Models\ApplicationCertifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ApplicationCertifier */
class ApplicationCertifierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'application_id' => $this->application_id,
            'certifier_id' => $this->certifier_id,
            'certifier_application_no' => $this->certifier_application_no,
            'decision' => $this->decision,
            'is_primary' => (bool) $this->is_primary,
            'source' => $this->source,
            'certifier' => CertifierResource::make($this->whenLoaded('certifier')),
            'application' => ApplicationResource::make($this->whenLoaded('application')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
