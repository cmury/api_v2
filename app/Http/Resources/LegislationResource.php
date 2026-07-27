<?php

namespace App\Http\Resources;

use App\Models\Legislation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Legislation */
class LegislationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_title' => $this->short_title,
            'display_name' => $this->display_name,
            'abbrev' => $this->abbrev,
            'jurisdiction' => $this->jurisdiction,
            'description' => $this->description,
            'instrument_type' => $this->instrument_type,
            'year' => $this->year,
            'revision' => $this->revision,
            'status' => $this->status,
            'effective_from' => $this->effective_from,
            'repealed_at' => $this->repealed_at,
            'url' => $this->url,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
