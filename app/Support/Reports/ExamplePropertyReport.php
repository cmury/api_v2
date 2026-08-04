<?php

namespace App\Support\Reports;

use App\Models\ReportPurchase;

/**
 * Demo dataset used when warehouse data is unavailable (or for the example report).
 */
final class ExamplePropertyReport
{
    /**
     * @return array<string, mixed>
     */
    public static function data(
        ?string $address = null,
        ?float $lat = null,
        ?float $lng = null,
        ?ReportPurchase $purchase = null,
    ): array {
        return [
            'title' => 'IMBY Property Report',
            'subtitle' => 'Example planning & development summary',
            'generated_at' => now()->timezone(config('app.timezone'))->toDayDateTimeString(),
            'is_example' => true,
            'purchase' => [
                'token' => $purchase?->download_token,
                'paid_at' => optional($purchase?->paid_at)?->toDateTimeString(),
                'amount_display' => $purchase
                    ? strtoupper($purchase->currency).' '.number_format($purchase->amount_cents / 100, 2)
                    : 'AUD 29.00',
            ],
            'property' => [
                'address' => $address ?: '12 Example Street, Surry Hills NSW 2010',
                'suburb' => 'Surry Hills',
                'state' => 'NSW',
                'post_code' => '2010',
                'lat' => $lat ?? -33.8837,
                'lng' => $lng ?? 151.2110,
                'location_id' => null,
                'street_no' => '12',
                'street' => 'Example Street',
            ],
            'planning_controls' => [
                [
                    'layer' => 'zoning',
                    'code' => 'R1',
                    'label' => 'General Residential',
                    'epi_name' => 'Sydney Local Environmental Plan 2012',
                    'lga_name' => 'City of Sydney',
                ],
                [
                    'layer' => 'height',
                    'code' => 'J',
                    'label' => '15m',
                    'epi_name' => 'Sydney Local Environmental Plan 2012',
                    'lga_name' => 'City of Sydney',
                ],
                [
                    'layer' => 'fsr',
                    'code' => 'T',
                    'label' => '1.5:1',
                    'epi_name' => 'Sydney Local Environmental Plan 2012',
                    'lga_name' => 'City of Sydney',
                ],
                [
                    'layer' => 'heritage_epi',
                    'code' => 'C',
                    'label' => 'Heritage Conservation Area',
                    'epi_name' => 'Sydney Local Environmental Plan 2012',
                    'lga_name' => 'City of Sydney',
                ],
            ],
            'applications' => [
                [
                    'authority_no' => 'D/2022/1234',
                    'description' => 'Alterations and additions to dwelling including rear extension.',
                    'submitted' => '2022-03-14',
                    'decision' => 'Approved',
                    'decision_date' => '2022-06-01',
                    'estimated_cost' => 185000,
                    'authority' => 'City of Sydney',
                    'tracking_url' => null,
                ],
                [
                    'authority_no' => 'D/2018/556',
                    'description' => 'Secondary dwelling above existing garage.',
                    'submitted' => '2018-09-02',
                    'decision' => 'Refused',
                    'decision_date' => '2018-11-20',
                    'estimated_cost' => 95000,
                    'authority' => 'City of Sydney',
                    'tracking_url' => null,
                ],
            ],
            'summary' => [
                'planning_control_count' => 4,
                'application_count' => 2,
                'authority' => 'City of Sydney',
            ],
            'disclaimer' => 'EXAMPLE REPORT — sample data for demonstration only. Not a live planning search. Verify all controls with the responsible authority.',
        ];
    }
}
