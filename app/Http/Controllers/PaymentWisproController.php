<?php

namespace App\Http\Controllers;

use App\Exports\WisproPaymentsExport;
use App\Helpers\BncHelper;
use App\Models\BcvRate;
use App\Models\PaymentWispro;
use App\Services\WisproApiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class PaymentWisproController extends Controller
{
    public function __construct(protected WisproApiService $wisproApiService)
    {
    }

    public function index(Request $request)
    {
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 20;
        $from = $request->get('from', Carbon::create(now()->year, 7, 1)->format('Y-m-d'));

        $payments = [
            'status' => 0,
            'meta' => [
                'object' => 'invoicing_payment',
                'pagination' => [
                    'total_records' => 0,
                    'total_pages' => 1,
                    'per_page' => $perPage,
                    'current_page' => $page,
                ],
            ],
            'data' => [],
        ];

        $response = $this->wisproApiService->getPayments($from, $page, $perPage, $from);

        if ($response['success']) {
            $payments = $response['data'];
        } else {
            Log::warning('Error al obtener pagos de Wispro API: ' . ($response['error'] ?? 'Error desconocido'));
        }


        return Inertia::render('PaymentsWispro/Index', [
            'payments' => $payments,
            'filters' => [
                'from' => $from,
            ],
        ]);
    }

    public function indexLocal(Request $request)
    {
        $from = $request->get('from');
        $to = $request->get('to');
        $clientCode = $request->get('client_code');
        $transactionKind = $request->get('transaction_kind');

        $paginator = $this->localPaymentsQuery($request)
            ->with('invoiceLinks')
            ->orderByDesc('payment_date')
            ->paginate(20)
            ->withQueryString();

        $pendingDownload = $this->localPaymentsQuery($request)
            ->where('download', false)
            ->count();

        $payments = [
            'status' => 200,
            'meta' => [
                'object' => 'payments_wispro_local',
                'pagination' => [
                    'total_records' => $paginator->total(),
                    'total_pages' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                ],
            ],
            'data' => $paginator->getCollection()->map(function (PaymentWispro $payment) {
                return [
                    'id' => $payment->id,
                    'wispro_id' => $payment->wispro_id,
                    'public_id' => $payment->public_id,
                    'client_name' => $payment->client_name,
                    'client_public_id' => $payment->client_public_id,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date?->toIso8601String(),
                    'transaction_kind' => $payment->transaction_kind,
                    'comment' => $payment->comment,
                    'state' => $payment->state,
                    'download' => (bool) $payment->download,
                    'payment_transactions' => $payment->invoiceLinks->map(fn ($link) => [
                        'id' => $link->wispro_transaction_id,
                        'invoice_id' => $link->invoice_id,
                        'invoice_number' => $link->invoice_number,
                        'amount' => $link->amount,
                    ])->values(),
                ];
            })->values(),
        ];

        return Inertia::render('PaymentsWispro/Local', [
            'payments' => $payments,
            'filters' => [
                'from' => $from ?? '',
                'to' => $to ?? '',
                'client_code' => $clientCode ?? '',
                'transaction_kind' => $transactionKind ?? '',
            ],
            'transaction_kinds' => PaymentWispro::query()
                ->where('state', '!=', 'void')
                ->whereNotNull('transaction_kind')
                ->distinct()
                ->orderBy('transaction_kind')
                ->pluck('transaction_kind'),
            'pending_download' => $pendingDownload,
        ]);
    }

    public function exportLocal(Request $request)
    {
        $payments = $this->localPaymentsQuery($request)
            ->where('download', false)
            ->with(['invoiceLinks.invoice.items'])
            ->orderBy('public_id')
            ->get();

        if ($payments->isEmpty()) {
            return back()->with('error', 'No hay pagos nuevos para descargar.');
        }

        $bcvRate = (float) (BcvRate::getLatestRate()['Rate'] ?? BncHelper::getBcvRatesCached()['Rate'] ?? 1);
        $rows = collect();
        $exportedIds = [];

        foreach ($payments as $payment) {
            $addedRow = false;

            foreach ($payment->invoiceLinks as $link) {
                $invoice = $link->invoice;

                if (!$invoice) {
                    continue;
                }

                foreach ($invoice->items as $item) {
                    $amountUsd = (float) $item->gross_amount;

                    $rows->push([
                        $payment->public_id,
                        $item->product_code ?: '',
                        number_format($amountUsd, 2, '.', ''),
                        number_format($amountUsd * $bcvRate, 2, '.', ''),
                    ]);
                    $addedRow = true;
                }
            }

            if ($addedRow) {
                $exportedIds[] = $payment->id;
            }
        }

        if ($rows->isEmpty()) {
            return back()->with('error', 'Los pagos pendientes no tienen ítems para exportar.');
        }

        PaymentWispro::whereIn('id', $exportedIds)->update(['download' => true]);

        $fileName = 'pagos_wispro_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new WisproPaymentsExport($rows), $fileName);
    }

    private function localPaymentsQuery(Request $request)
    {
        $from = $request->get('from');
        $to = $request->get('to');
        $clientCode = $request->get('client_code');
        $transactionKind = $request->get('transaction_kind');

        return PaymentWispro::query()
            ->where('state', '!=', 'void')
            ->when($from, fn ($query) => $query->whereDate('payment_date', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('payment_date', '<=', $to))
            ->when($clientCode, fn ($query) => $query->where('public_id', $clientCode))
            ->when($transactionKind, fn ($query) => $query->where('transaction_kind', $transactionKind));
    }

    public function localInvoices(PaymentWispro $payment)
    {
        $payment->load(['invoiceLinks.invoice.items']);

        $invoices = $payment->invoiceLinks
            ->map(fn ($link) => $link->invoice)
            ->filter()
            ->unique('id')
            ->values()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->wispro_id,
                    'invoice_number' => $invoice->invoice_number,
                    'client_name' => $invoice->client_name,
                    'client_national_identification_number' => $invoice->client_national_identification_number,
                    'invoicing_firm_company_name' => $invoice->invoicing_firm_company_name,
                    'from' => $invoice->period_from?->format('Y-m-d'),
                    'to' => $invoice->period_to?->format('Y-m-d'),
                    'issued_at' => $invoice->issued_at?->toIso8601String(),
                    'first_due_date' => $invoice->first_due_date?->format('Y-m-d'),
                    'state' => $invoice->state,
                    'gross_amount' => $invoice->gross_amount,
                    'net_amount' => $invoice->net_amount,
                    'tax_amount' => $invoice->tax_amount,
                    'items' => $invoice->items->map(fn ($item) => [
                        'id' => $item->wispro_id,
                        'description' => $item->description,
                        'product_code' => $item->product_code,
                        'quantity' => $item->quantity,
                        'net_amount' => $item->net_amount,
                        'tax_amount' => $item->tax_amount,
                        'gross_amount' => $item->gross_amount,
                    ])->values(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function invoices(Request $request)
    {
        $invoiceIds = collect($request->input('invoice_ids', []))
            ->filter()
            ->unique()
            ->values();

        if ($invoiceIds->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'No se enviaron facturas',
                'data' => [],
            ], 422);
        }

        $invoices = $invoiceIds->map(function ($invoiceId) {
            return $this->wisproApiService->getInvoiceById($invoiceId);
        })->all();

        return response()->json([
            'success' => true,
            'data' => $invoices,
        ]);
    }

    public function downloadPdf(string $invoiceId)
    {
        $result = $this->wisproApiService->downloadInvoicePdf($invoiceId);

        if (!($result['success'] ?? false) || empty($result['body'])) {
            abort(404, 'No se pudo obtener el PDF de la factura.');
        }

        return response($result['body'], 200, [
            'Content-Type' => $result['content_type'] ?? 'application/pdf',
            'Content-Disposition' => 'inline; filename="factura-' . $invoiceId . '.pdf"',
        ]);
    }
}
