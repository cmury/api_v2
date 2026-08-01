<?php

namespace App\Http\Resources;

use App\Models\PlanningControl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PlanningControl */
class PlanningControlResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $includeGeometry = $request->boolean('include_geometry')
            || $request->boolean('geometry')
            || $request->input('include') === 'geometry';

        $row = [
            'id' => $this->id,
            'source' => $this->source,
            'source_id' => $this->source_id,
            'layer' => $this->layer,
            'code' => $this->code,
            'label' => $this->label,
            'purpose' => $this->purpose,
            'epi_name' => $this->epi_name,
            'epi_type' => $this->epi_type,
            'lga_name' => $this->lga_name,
            'authority_id' => $this->authority_id,
            'authority' => AuthorityResource::make($this->whenLoaded('authority')),
            'source_modified_at' => $this->source_modified_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($includeGeometry && ! empty($this->geometry_geojson)) {
            $geometry = json_decode((string) $this->geometry_geojson, true);
            if (is_array($geometry)) {
                $row['geometry'] = $geometry;
            }
        }

        if ($request->boolean('include_payload')) {
            $row['payload'] = $this->payload;
        }

        return $row;
    }
}
