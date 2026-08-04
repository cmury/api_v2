<?php

namespace App\Support\Reports;

use App\Models\ReportPurchase;
use Dompdf\Dompdf;
use Dompdf\Options;

final class PropertyReportPdf
{
    public function __construct(
        private readonly PropertyReportBuilder $builder,
    ) {}

    /**
     * @return array{binary: string, filename: string, data: array<string, mixed>}
     */
    public function render(ReportPurchase $purchase): array
    {
        $data = $this->builder->build($purchase);
        $html = view('reports.property', $data)->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $suburb = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($data['property']['suburb'] ?? 'property')) ?: 'property';
        $filename = sprintf('imby-property-report-%s-%s.pdf', strtolower($suburb), now()->format('Ymd'));

        return [
            'binary' => $dompdf->output(),
            'filename' => $filename,
            'data' => $data,
        ];
    }
}
