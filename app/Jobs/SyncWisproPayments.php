<?php

namespace App\Jobs;

use App\Models\InvoiceItemWispro;
use App\Models\InvoiceWispro;
use App\Models\PaymentInvoiceWispro;
use App\Models\PaymentWispro;
use App\Services\WisproApiService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncWisproPayments implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 900;
    public int $uniqueFor = 1800;

    public const PER_PAGE = 100;
    public const LOOKBACK_DAYS = 7;

    public function handle(WisproApiService $wisproApiService): void
    {
        $from = now()->subDays(self::LOOKBACK_DAYS)->toDateString();
        $to = now()->toDateString();

        $page = 1;
        $createdPayments = 0;
        $updatedPayments = 0;
        $createdInvoices = 0;
        $skippedInvoices = 0;
        $failedInvoices = 0;

        Log::info('Sync Wispro payments: inicio', compact('from', 'to'));

        do {
            $response = $wisproApiService->getPayments($from, $page, self::PER_PAGE, $to);

            if (!($response['success'] ?? false)) {
                throw new \Exception($response['error'] ?? 'Error al obtener pagos de Wispro');
            }

            $payload = $response['data'] ?? [];
            $payments = $payload['data'] ?? [];
            $totalPages = $payload['meta']['pagination']['total_pages'] ?? 1;

            foreach ($payments as $paymentData) {
                $result = $this->storePayment($wisproApiService, $paymentData);
                $createdPayments += $result['created_payment'];
                $updatedPayments += $result['updated_payment'];
                $createdInvoices += $result['created_invoices'];
                $skippedInvoices += $result['skipped_invoices'];
                $failedInvoices += $result['failed_invoices'];
            }

            $page++;
        } while ($page <= $totalPages);

        Log::info('Sync Wispro payments: fin', [
            'from' => $from,
            'to' => $to,
            'created_payments' => $createdPayments,
            'updated_payments' => $updatedPayments,
            'created_invoices' => $createdInvoices,
            'skipped_invoices' => $skippedInvoices,
            'failed_invoices' => $failedInvoices,
        ]);
    }

    private function storePayment(WisproApiService $wisproApiService, array $paymentData): array
    {
        $result = [
            'created_payment' => 0,
            'updated_payment' => 0,
            'created_invoices' => 0,
            'skipped_invoices' => 0,
            'failed_invoices' => 0,
        ];

        $wisproId = $paymentData['id'] ?? null;

        if (!$wisproId || ($paymentData['state'] ?? '') === 'void') {
            return $result;
        }

        $payment = PaymentWispro::updateOrCreate(
            ['wispro_id' => $wisproId],
            [
                'public_id' => $paymentData['public_id'] ?? null,
                'client_id' => $paymentData['client_id'] ?? null,
                'client_name' => $paymentData['client_name'] ?? null,
                'client_public_id' => $paymentData['client_public_id'] ?? null,
                'amount' => $paymentData['amount'] ?? 0,
                'credit_amount' => $paymentData['credit_amount'] ?? 0,
                'payment_date' => $this->parseDate($paymentData['payment_date'] ?? null),
                'comment' => $paymentData['comment'] ?? null,
                'transaction_kind' => $paymentData['transaction_kind'] ?? null,
                'transaction_code' => $paymentData['transaction_code'] ?? null,
                'state' => $paymentData['state'] ?? null,
                'name_user' => $paymentData['name_user'] ?? null,
                'name_collector' => $paymentData['name_collector'] ?? null,
                'wispro_created_at' => $this->parseDate($paymentData['created_at'] ?? null),
                'wispro_updated_at' => $this->parseDate($paymentData['updated_at'] ?? null),
            ]
        );

        $result[$payment->wasRecentlyCreated ? 'created_payment' : 'updated_payment'] = 1;

        foreach ($paymentData['payment_transactions'] ?? [] as $transaction) {
            $invoiceResult = $this->storeInvoice($wisproApiService, $transaction['invoice_id'] ?? null);
            $result['created_invoices'] += $invoiceResult['created'];
            $result['skipped_invoices'] += $invoiceResult['skipped'];
            $result['failed_invoices'] += $invoiceResult['failed'];

            if (empty($transaction['id'])) {
                continue;
            }

            PaymentInvoiceWispro::updateOrCreate(
                ['wispro_transaction_id' => $transaction['id']],
                [
                    'payment_wispro_id' => $payment->id,
                    'invoice_wispro_id' => $invoiceResult['invoice']?->id,
                    'invoice_id' => $transaction['invoice_id'],
                    'invoice_number' => $transaction['invoice_number'] ?? null,
                    'amount' => $transaction['amount'] ?? 0,
                ]
            );
        }

        return $result;
    }

    private function storeInvoice(WisproApiService $wisproApiService, ?string $invoiceId): array
    {
        if (!$invoiceId) {
            return ['invoice' => null, 'created' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $existing = InvoiceWispro::where('wispro_id', $invoiceId)->first();

        if ($existing) {
            return ['invoice' => $existing, 'created' => 0, 'skipped' => 1, 'failed' => 0];
        }

        $response = $wisproApiService->getInvoiceById($invoiceId);
        $invoiceData = $response['data']['data'] ?? null;

        if (!($response['success'] ?? false) || !$invoiceData) {
            Log::warning('Sync Wispro payments: no se pudo obtener factura', [
                'invoice_id' => $invoiceId,
                'error' => $response['error'] ?? 'sin data',
            ]);

            return ['invoice' => null, 'created' => 0, 'skipped' => 0, 'failed' => 1];
        }

        $invoice = InvoiceWispro::updateOrCreate(
            ['wispro_id' => $invoiceData['id']],
            [
                'contract_id' => $invoiceData['contract_id'] ?? null,
                'invoicing_firm_id' => $invoiceData['invoicing_firm_id'] ?? null,
                'kind_invoice' => $invoiceData['kind_invoice'] ?? null,
                'invoice_number' => $invoiceData['invoice_number'] ?? null,
                'period_from' => $this->parseDate($invoiceData['from'] ?? null, true),
                'period_to' => $this->parseDate($invoiceData['to'] ?? null, true),
                'issued_at' => $this->parseDate($invoiceData['issued_at'] ?? null),
                'first_due_date' => $this->parseDate($invoiceData['first_due_date'] ?? null, true),
                'second_due_date' => $this->parseDate($invoiceData['second_due_date'] ?? null, true),
                'state' => $invoiceData['state'] ?? null,
                'amount' => $invoiceData['amount'] ?? 0,
                'gross_amount' => $invoiceData['gross_amount'] ?? 0,
                'tax_amount' => $invoiceData['tax_amount'] ?? 0,
                'discount_amount' => $invoiceData['discount_amount'] ?? 0,
                'net_amount' => $invoiceData['net_amount'] ?? 0,
                'balance' => $invoiceData['balance'] ?? 0,
                'invoicing_firm_company_name' => $invoiceData['invoicing_firm_company_name'] ?? null,
                'client_name' => $invoiceData['client_name'] ?? null,
                'client_national_identification_number' => $invoiceData['client_national_identification_number'] ?? null,
                'client_email' => $invoiceData['client_email'] ?? null,
                'client_phone' => $invoiceData['client_phone'] ?? null,
                'wispro_created_at' => $this->parseDate($invoiceData['created_at'] ?? null),
                'wispro_updated_at' => $this->parseDate($invoiceData['updated_at'] ?? null),
            ]
        );

        foreach ($invoiceData['items'] ?? [] as $item) {
            if (empty($item['id'])) {
                continue;
            }

            InvoiceItemWispro::updateOrCreate(
                ['wispro_id' => $item['id']],
                [
                    'invoice_wispro_id' => $invoice->id,
                    'invoice_wispro_uuid' => $item['invoice_id'] ?? $invoice->wispro_id,
                    'description' => $item['description'] ?? null,
                    'product_code' => $item['product_code'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'amount' => $item['amount'] ?? 0,
                    'gross_amount' => $item['gross_amount'] ?? 0,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'net_amount' => $item['net_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ]
            );
        }

        return ['invoice' => $invoice, 'created' => 1, 'skipped' => 0, 'failed' => 0];
    }

    private function parseDate(?string $value, bool $dateOnly = false): ?string
    {
        if (!$value) {
            return null;
        }

        try {
            $date = Carbon::parse($value);

            return $dateOnly ? $date->toDateString() : $date->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Sync Wispro payments falló: ' . $exception->getMessage());
    }
}
