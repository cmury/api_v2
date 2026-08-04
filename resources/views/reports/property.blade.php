<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28mm 18mm 22mm 18mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            line-height: 1.45;
        }
        h1 { font-size: 22px; margin: 0 0 4px; letter-spacing: -0.02em; }
        h2 { font-size: 13px; margin: 22px 0 8px; border-bottom: 1px solid #ccc; padding-bottom: 4px; text-transform: uppercase; letter-spacing: 0.06em; }
        .muted { color: #666; }
        .banner {
            background: #111;
            color: #fff;
            padding: 14px 16px;
            margin: 0 0 18px;
        }
        .banner .brand { font-size: 12px; letter-spacing: 0.18em; text-transform: uppercase; opacity: 0.75; }
        .meta { margin-bottom: 16px; }
        .meta td { padding: 2px 12px 2px 0; vertical-align: top; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        table.data th { background: #f4f4f4; font-weight: 600; }
        .pill {
            display: inline-block;
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 10px;
        }
        .example {
            background: #fff3cd;
            border: 1px solid #e6c200;
            padding: 8px 10px;
            margin-bottom: 14px;
        }
        .disclaimer {
            margin-top: 28px;
            font-size: 9px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .summary-grid td { width: 33%; padding: 8px 10px; background: #fafafa; border: 1px solid #eee; }
        .summary-grid .label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.08em; color: #777; }
        .summary-grid .value { font-size: 16px; font-weight: 600; margin-top: 2px; }
    </style>
</head>
<body>
    <div class="banner">
        <div class="brand">IMBY</div>
        <h1>{{ $title }}</h1>
        <div class="muted" style="color:#ccc;">{{ $subtitle }}</div>
    </div>

    @if (!empty($is_example))
        <div class="example">
            <strong>Example report</strong> — sample planning and application data for demonstration.
            Live purchases use warehouse records for the selected property.
        </div>
    @endif

    <table class="meta">
        <tr>
            <td><strong>Property</strong><br>{{ $property['address'] }}</td>
            <td><strong>Generated</strong><br>{{ $generated_at }}</td>
            <td><strong>Paid</strong><br>{{ $purchase['amount_display'] ?? '—' }}<br><span class="muted">{{ $purchase['paid_at'] ?? '' }}</span></td>
        </tr>
    </table>

    <table class="summary-grid" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td>
                <div class="label">Authority / LGA</div>
                <div class="value">{{ $summary['authority'] ?? '—' }}</div>
            </td>
            <td>
                <div class="label">Planning controls</div>
                <div class="value">{{ $summary['planning_control_count'] ?? 0 }}</div>
            </td>
            <td>
                <div class="label">Development applications</div>
                <div class="value">{{ $summary['application_count'] ?? 0 }}</div>
            </td>
        </tr>
    </table>

    <h2>Property details</h2>
    <table class="data">
        <tr><th>Address</th><td>{{ $property['address'] }}</td></tr>
        <tr><th>Suburb</th><td>{{ $property['suburb'] ?? '—' }}</td></tr>
        <tr><th>State / postcode</th><td>{{ trim(($property['state'] ?? '').' '.($property['post_code'] ?? '')) ?: '—' }}</td></tr>
        <tr><th>Coordinates</th><td>
            @if ($property['lat'] !== null && $property['lng'] !== null)
                {{ number_format((float) $property['lat'], 5) }}, {{ number_format((float) $property['lng'], 5) }}
            @else
                —
            @endif
        </td></tr>
        @if (!empty($property['location_id']))
            <tr><th>IMBY location ID</th><td>{{ $property['location_id'] }}</td></tr>
        @endif
    </table>

    <h2>Planning controls</h2>
    @if (empty($planning_controls))
        <p class="muted">No planning controls found for this point.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Layer</th>
                    <th>Code</th>
                    <th>Label</th>
                    <th>Instrument</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($planning_controls as $row)
                    <tr>
                        <td><span class="pill">{{ $row['layer'] }}</span></td>
                        <td>{{ $row['code'] ?? '—' }}</td>
                        <td>{{ $row['label'] ?? '—' }}</td>
                        <td>{{ $row['epi_name'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Development applications</h2>
    @if (empty($applications))
        <p class="muted">No development applications linked to this location.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Submitted</th>
                    <th>Decision</th>
                    <th>Cost</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($applications as $app)
                    <tr>
                        <td>{{ $app['authority_no'] ?? '—' }}<br><span class="muted">{{ $app['authority'] ?? '' }}</span></td>
                        <td>{{ $app['submitted'] ?? '—' }}</td>
                        <td>{{ $app['decision'] ?? '—' }}<br><span class="muted">{{ $app['decision_date'] ?? '' }}</span></td>
                        <td>
                            @if ($app['estimated_cost'] !== null)
                                ${{ number_format((float) $app['estimated_cost'], 0) }}
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $app['description'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="disclaimer">
        {{ $disclaimer }}
        @if (!empty($purchase['token']))
            <br>Purchase reference: {{ $purchase['token'] }}
        @endif
    </div>
</body>
</html>
