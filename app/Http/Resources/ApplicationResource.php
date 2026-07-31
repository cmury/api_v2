<?php

namespace App\Http\Resources;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Application */
class ApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'authority_id' => $this->authority_id,
            'authority_no' => $this->authority_no,
            'portal_no' => $this->portal_no,
            'type' => $this->type,
            'description' => $this->description,
            'estimated_cost' => $this->estimated_cost,
            'submitted' => $this->submitted,
            'decision' => $this->decision,
            'decision_authority' => $this->decision_authority,
            'decision_date' => $this->decision_date,
            'tracking_url' => $this->tracking_url,
            'contact_email' => $this->contact_email,
            'record_extracted' => $this->record_extracted,
            'authority_record_modified' => $this->authority_record_modified,
            'authority' => AuthorityResource::make($this->whenLoaded('authority')),
            'locations' => LocationResource::collection($this->whenLoaded('locations')),
            'application_types' => $this->whenLoaded('applicationTypes', fn () => $this->applicationTypes->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'display_name' => $t->display_name,
                'class' => $t->relationLoaded('applicationClass') && $t->applicationClass
                    ? ['id' => $t->applicationClass->id, 'name' => $t->applicationClass->name]
                    : null,
            ])),
            'development_types' => $this->whenLoaded('developmentTypes', fn () => $this->developmentTypes->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'display_name' => $t->display_name,
                'class' => $t->relationLoaded('developmentClass') && $t->developmentClass
                    ? ['id' => $t->developmentClass->id, 'name' => $t->developmentClass->name]
                    : null,
            ])),
            'decision_types' => $this->whenLoaded('decisionTypes', fn () => $this->decisionTypes->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'display_name' => $t->display_name,
                'class' => $t->relationLoaded('decisionClass') && $t->decisionClass
                    ? ['id' => $t->decisionClass->id, 'name' => $t->decisionClass->name]
                    : null,
            ])),
            'legislation' => LegislationResource::collection($this->whenLoaded('legislations')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
