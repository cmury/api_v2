<?php

namespace App\Support\Reports;

use App\Models\Application;
use App\Models\Location;
use App\Models\ReportPurchase;
use App\Support\Warehouse\PlanningControlSearch;
use Throwable;

/**
 * Assembles warehouse data for a property report PDF.
 */
final class PropertyReportBuilder
{
    public function __construct(
        private readonly PlanningControlSearch $planningControls = new PlanningControlSearch,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(ReportPurchase $purchase): array
    {
        $location = null;
        $lat = $purchase->lat;
        $lng = $purchase->lng;
        $address = $purchase->formatted_address;

        if ($purchase->location_id) {
            try {
                $location = Location::query()->find($purchase->location_id);
            } catch (Throwable) {
                $location = null;
            }
        }

        if ($location) {
            $address = $address ?: $location->formatted_address;
            if ($lat === null || $lng === null) {
                try {
                    $coords = Location::query()
                        ->whereKey($location->id)
                        ->whereNotNull('geom')
                        ->selectRaw('ST_Y(geom::geometry) AS lat, ST_X(geom::geometry) AS lng')
                        ->first();
                    if ($coords) {
                        $lat = (float) $coords->lat;
                        $lng = (float) $coords->lng;
                    }
                } catch (Throwable) {
                    // PostGIS unavailable — fall through to example / address-only.
                }
            }
        }

        $planning = [];
        if ($lat !== null && $lng !== null) {
            try {
                $planning = $this->planningControls
                    ->atPoint((float) $lat, (float) $lng, limit: 40)
                    ->get()
                    ->map(fn ($row) => [
                        'layer' => $row->layer,
                        'code' => $row->code,
                        'label' => $row->label,
                        'epi_name' => $row->epi_name,
                        'lga_name' => $row->lga_name,
                    ])
                    ->all();
            } catch (Throwable) {
                $planning = [];
            }
        }

        $applications = [];
        if ($location) {
            try {
                $applications = $location->applications()
                    ->with(['authority'])
                    ->orderByDesc('submitted')
                    ->limit(25)
                    ->get()
                    ->map(fn (Application $app) => [
                        'authority_no' => $app->authority_no,
                        'description' => $app->description,
                        'submitted' => optional($app->submitted)?->toDateString(),
                        'decision' => $app->decision,
                        'decision_date' => optional($app->decision_date)?->toDateString(),
                        'estimated_cost' => $app->estimated_cost,
                        'authority' => $app->authority?->name,
                        'tracking_url' => $app->tracking_url,
                    ])
                    ->all();
            } catch (Throwable) {
                $applications = [];
            }
        }

        $useExample = $location === null && $planning === [] && $applications === [];

        if ($useExample) {
            return ExamplePropertyReport::data(
                address: $address ?: '12 Example Street, Surry Hills NSW 2010',
                lat: $lat,
                lng: $lng,
                purchase: $purchase,
            );
        }

        return [
            'title' => 'IMBY Property Report',
            'subtitle' => 'Planning & development summary',
            'generated_at' => now()->timezone(config('app.timezone'))->toDayDateTimeString(),
            'is_example' => false,
            'purchase' => [
                'token' => $purchase->download_token,
                'paid_at' => optional($purchase->paid_at)?->toDateTimeString(),
                'amount_display' => $this->formatMoney($purchase->amount_cents, $purchase->currency),
            ],
            'property' => [
                'address' => $address ?: 'Address unavailable',
                'suburb' => $location?->suburb,
                'state' => $location?->state,
                'post_code' => $location?->post_code,
                'lat' => $lat,
                'lng' => $lng,
                'location_id' => $location?->id,
                'street_no' => $location?->street_no,
                'street' => $location?->street,
            ],
            'planning_controls' => $planning,
            'applications' => $applications,
            'summary' => [
                'planning_control_count' => count($planning),
                'application_count' => count($applications),
                'authority' => $applications[0]['authority'] ?? ($planning[0]['lga_name'] ?? null),
            ],
            'disclaimer' => 'This report is generated from IMBY warehouse data for informational purposes. Verify controls and decisions with the responsible planning authority before relying on this summary.',
        ];
    }

    private function formatMoney(int $cents, string $currency): string
    {
        return strtoupper($currency).' '.number_format($cents / 100, 2);
    }
}
