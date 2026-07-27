<?php

namespace App\Http\Requests\Warehouse;

use App\Support\Warehouse\StatsQuery;
use Illuminate\Validation\Rule;

class ChartRequest extends StatsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'metric' => ['required', 'string', Rule::in(StatsQuery::CHART_METRICS)],
            'interval' => ['nullable', 'string', Rule::in(['day', 'week', 'month', 'year'])],
            'format' => ['nullable', 'string', Rule::in(['auto', 'timeseries', 'calendar', 'categorical', 'bands'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            ...$this->filterRules(),
        ];
    }
}
