<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StartPropertyReportPaymentRequest;
use App\Models\ReportPurchase;
use App\Support\Reports\ExamplePropertyReport;
use App\Support\Reports\PropertyReportCheckout;
use App\Support\Reports\PropertyReportPdf;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class PropertyReportController extends Controller
{
    public function __construct(
        private readonly PropertyReportCheckout $checkout,
        private readonly PropertyReportPdf $pdf,
    ) {}

    public function pricing(): JsonResponse
    {
        $cents = (int) config('imby.reports.property.amount_cents', 2900);
        $currency = strtolower((string) config('imby.reports.property.currency', 'aud'));

        return response()->json([
            'message' => 'property_report_pricing',
            'data' => [
                'product' => 'property_report',
                'amount_cents' => $cents,
                'currency' => $currency,
                'amount_display' => strtoupper($currency).' '.number_format($cents / 100, 2),
                'description' => (string) config(
                    'imby.reports.property.description',
                    'One-time property planning & development report PDF',
                ),
                'publishable_key' => config('cashier.key'),
            ],
        ]);
    }

    /**
     * Free example PDF (no payment) so the SPA can preview the template.
     */
    public function example(): SymfonyResponse
    {
        $data = ExamplePropertyReport::data();
        $html = view('reports.property', $data)->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="imby-property-report-example.pdf"',
        ]);
    }

    public function pay(StartPropertyReportPaymentRequest $request): JsonResponse
    {
        try {
            $result = $this->checkout->start($request->validated());
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'payment_intent_failed',
                'errors' => [
                    'payment' => ['Unable to start payment. Check Stripe configuration and try again.'],
                ],
            ], 502);
        }

        $purchase = $result['purchase'];

        return response()->json([
            'message' => 'payment_intent_created',
            'data' => [
                'download_token' => $purchase->download_token,
                'client_secret' => $result['client_secret'],
                'publishable_key' => $result['publishable_key'],
                'amount_cents' => $purchase->amount_cents,
                'currency' => $purchase->currency,
                'status' => $purchase->status,
                'expires_at' => $purchase->expires_at,
            ],
        ], 201);
    }

    public function status(string $token): JsonResponse
    {
        $purchase = $this->findByToken($token);

        if ($purchase === null) {
            return response()->json(['message' => 'purchase_not_found'], 404);
        }

        if ($purchase->isPending()) {
            try {
                $purchase = $this->checkout->syncFromStripe($purchase);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response()->json([
            'message' => 'property_report_status',
            'data' => [
                'download_token' => $purchase->download_token,
                'status' => $purchase->status,
                'paid_at' => $purchase->paid_at,
                'address' => $purchase->formatted_address,
                'download_ready' => $purchase->isPaid(),
            ],
        ]);
    }

    public function download(string $token): SymfonyResponse|JsonResponse
    {
        $purchase = $this->findByToken($token);

        if ($purchase === null) {
            return response()->json(['message' => 'purchase_not_found'], 404);
        }

        if ($purchase->isPending()) {
            try {
                $purchase = $this->checkout->syncFromStripe($purchase);
            } catch (Throwable $e) {
                report($e);
            }
        }

        if (! $purchase->isPaid()) {
            return response()->json([
                'message' => 'payment_required',
                'errors' => [
                    'download_token' => ['Payment has not completed for this report.'],
                ],
            ], 402);
        }

        try {
            $rendered = $this->pdf->render($purchase);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'report_generation_failed',
                'errors' => [
                    'report' => ['Unable to generate the property report PDF.'],
                ],
            ], 500);
        }

        return response($rendered['binary'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$rendered['filename'].'"',
        ]);
    }

    /**
     * Stripe webhook for report PaymentIntents (public, signature-verified).
     */
    public function webhook(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('imby.reports.webhook_secret') ?: config('cashier.webhook.secret');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature ?? '',
                (string) $secret,
            );
        } catch (Throwable $e) {
            return response('Invalid signature', 400);
        }

        if ($event->type === 'payment_intent.succeeded') {
            $intent = $event->data->object;
            $this->checkout->markPaidByPaymentIntent((string) $intent->id);
        }

        if ($event->type === 'payment_intent.payment_failed') {
            $intent = $event->data->object;
            ReportPurchase::query()
                ->where('stripe_payment_intent_id', $intent->id)
                ->where('status', ReportPurchase::STATUS_PENDING)
                ->update(['status' => ReportPurchase::STATUS_FAILED]);
        }

        return response('ok', 200);
    }

    private function findByToken(string $token): ?ReportPurchase
    {
        if ($token === '' || strlen($token) < 20) {
            return null;
        }

        return ReportPurchase::query()
            ->where('download_token', $token)
            ->first();
    }
}
