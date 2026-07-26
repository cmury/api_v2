<?php

namespace App\Http\Resources;

use App\Models\AuthorityStatistic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AuthorityStatistic */
class AuthorityStatisticResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'statistics_code' => $this->statistics_code,
            // Legacy alias used by imby_v2 AuthorityStatistic type.
            'authority_code' => $this->statistics_code,
            'measure' => $this->measure,
            'year' => $this->year,
            'value' => $this->value,
            'units' => $this->units,
            'source' => $this->source,
            'dataflow' => $this->dataflow,
            'sex' => $this->sex !== '' ? $this->sex : null,
        ];
    }
}
