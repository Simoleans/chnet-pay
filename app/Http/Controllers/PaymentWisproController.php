<?php

namespace App\Http\Controllers;

use App\Services\WisproApiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

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
