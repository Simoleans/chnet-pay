<?php

namespace App\Http\Controllers;

use App\Helpers\BncHelper;
use App\Models\Payment;
use App\Models\User;
use App\Services\WisproApiService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DashboardController extends Controller
{
    protected $wisproService;

    public function __construct(WisproApiService $wisproService)
    {
        $this->wisproService = $wisproService;
    }

    /**
     * Mostrar el dashboard según el rol del usuario
     */
    public function index()
    {
        $user = Auth::user();
        $data = [];

        // Si el usuario tiene role = 0 (usuario), cargar sus datos
        if ($user && $user->role === 0) {
            $data = $this->getUserDashboardData($user);
        }

        // Si el usuario es admin (role = 1), cargar estadísticas
        if ($user && $user->role === 1) {
            $data = array_merge($data, $this->getAdminDashboardData());
        }

        return Inertia::render('Dashboard', $data);
    }

    /**
     * Obtener datos del dashboard para usuario normal
     */
    private function getUserDashboardData($user)
    {
        $data = [];

        // Obtener pagos del usuario
        $payments = $user->payments()
            ->with(['invoice'])
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        $data['user_payments'] = $this->formatPayments($payments);

        // Obtener contrato y plan desde Wispro
        if ($user->id_wispro) {
            $contractData = $this->getUserContractAndPlan($user->id_wispro);
            $data = array_merge($data, $contractData);
        }

        // Obtener facturas de Wispro
        if ($user->code) {
            $invoicesData = $this->getUserInvoices($user->code);
            $data = array_merge($data, $invoicesData);

            // Agregar alerta si hay facturas pendientes
            if (isset($invoicesData['pending_invoices_count']) && $invoicesData['pending_invoices_count'] > 0) {
                $data['show_pending_invoice_alert'] = true;
            }
        } else {
            $data['user_invoices'] = [];
            $data['pending_invoices_count'] = 0;
        }

        return $data;
    }

    /**
     * Obtener datos del dashboard para administrador
     */
    private function getAdminDashboardData()
    {
        $data = [];

        // Total de clientes locales
        $data['total_clients'] = User::where('role', 0)->count();

        // Estadísticas de contratos por estado
        $data['contract_stats'] = $this->getContractStats();

        // Pagos recientes
        $allPayments = Payment::with(['user', 'invoice'])
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $data['admin_payments'] = $this->formatPayments($allPayments);

        return $data;
    }

    /**
     * Obtener contrato y plan del usuario
     */
    private function getUserContractAndPlan($wisproId)
    {
        $data = [];

        $contractsResponse = $this->wisproService->getClientContracts($wisproId);

        if ($contractsResponse['success'] && !empty($contractsResponse['data']['data'])) {
            $contractData = $contractsResponse['data']['data'][0];

            // Obtener plan si existe
            $plan = null;
            if (!empty($contractData['plan_id'])) {
                $planResponse = $this->wisproService->getPlanById($contractData['plan_id']);

                if ($planResponse['success']) {
                    $planData = $planResponse['data']['data'] ?? $planResponse['data'];
                    $plan = [
                        'name' => $planData['name'] ?? 'N/A',
                        'price' => $planData['price'] ?? 0,
                    ];
                }
            }

            $data['user_contract'] = [
                'start_date' => $contractData['start_date'] ?? null,
                'latitude' => $contractData['latitude'] ?? null,
                'longitude' => $contractData['longitude'] ?? null,
                'state' => $contractData['state'] ?? null,
            ];

            $data['user_plan'] = $plan;
        }

        return $data;
    }

    /**
     * Obtener facturas del usuario
     */
    private function getUserInvoices($userCode)
    {
        $data = [];

        $invoicesResponse = $this->wisproService->getInvoicesByCustomId($userCode, 1, 10);

        if ($invoicesResponse['success'] && !empty($invoicesResponse['data']['data'])) {
            $data['user_invoices'] = $invoicesResponse['data']['data'];

            // Contar facturas pendientes
            $pendingInvoices = collect($invoicesResponse['data']['data'])->filter(function ($invoice) {
                return $invoice['state'] === 'pending';
            })->count();

            $data['pending_invoices_count'] = $pendingInvoices;
        } else {
            $data['user_invoices'] = [];
            $data['pending_invoices_count'] = 0;
        }

        return $data;
    }

    /**
     * Obtener estadísticas de contratos por estado
     */
    private function getContractStats()
    {
        $contractStates = ['enabled', 'disabled', 'degraded', 'alerted'];
        $contractStats = [];

        foreach ($contractStates as $state) {
            $response = $this->wisproService->getContractsByState($state, 1, 1);
            if ($response['success'] && isset($response['data']['meta']['pagination']['total_records'])) {
                $contractStats[$state] = $response['data']['meta']['pagination']['total_records'];
            } else {
                $contractStats[$state] = 0;
            }
        }

        return $contractStats;
    }

    /**
     * Formatear pagos para mostrar en el frontend
     */
    private function formatPayments($payments)
    {
        return $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'amount_bs' => $payment->amount * (BncHelper::getBcvRatesCached()['Rate'] ?? 1),
                'payment_date' => $payment->payment_date ? $payment->payment_date->format('d/m/Y') : null,
                'bank' => $payment->bank,
                'phone' => $payment->phone,
                'id_number' => $payment->id_number,
                'user_name' => $payment->user ? $payment->user->name : 'N/A',
                'user_code' => $payment->user ? $payment->user->code : 'N/A',
                'invoice_period' => $payment->invoice && $payment->invoice->period ?
                    $payment->invoice->period->format('Y-m') : 'Sin factura',
                'created_at' => $payment->created_at ? $payment->created_at->format('d/m/Y H:i') : null,
                'image_path' => $payment->image_path,
                'verify_payments' => $payment->verify_payments,
            ];
        });
    }
}

