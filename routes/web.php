<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
//use App\Http\Controllers\TestApiController;
use App\Http\Controllers\{PlanController,UserController,ZoneController,PaymentController,ClientImportController,PublicPaymentController};
use App\Helpers\BncHelper;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
/* use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
 */

// Rutas públicas (sin autenticación)
Route::get('/pagar/{code}', [PublicPaymentController::class, 'show'])->name('public-payment.show');
Route::post('/pagar', [PublicPaymentController::class, 'store'])->name('public-payment.store');

Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('home');

//Route::post('pay-fee', [TestApiController::class, 'testApi'])->name('pay-fee');

//plans
Route::resource('plans', PlanController::class)->middleware(['auth', 'admin']);

//users - Solo administradores
Route::resource('users', UserController::class)->middleware(['auth', 'admin']);
Route::put('/users/{id}/update-client', [UserController::class, 'updateClient'])->middleware(['auth', 'admin'])->name('users.update-client');
Route::get('/users/wispro/{wisproId}/show', [UserController::class, 'showWispro'])->middleware(['auth', 'admin'])->name('users.show-wispro');
Route::post('/users/wispro/{wisproId}/sync', [UserController::class, 'syncSingleWisproClient'])->middleware(['auth', 'admin'])->name('users.sync-wispro-single');

//zones
Route::resource('zones', ZoneController::class)->middleware(['auth', 'admin']);

//payments - Solo administradores
Route::resource('payments', PaymentController::class)->middleware(['auth', 'admin']);
Route::patch('/payments/{payment}/verify', [PaymentController::class, 'toggleVerification'])->middleware(['auth', 'admin'])->name('payments.toggle-verification');
Route::get('/payments-export', [PaymentController::class, 'export'])->middleware(['auth', 'admin'])->name('payments.export');

// Ruta especial para pago rápido desde login (sin autenticación)
Route::post('/quick-payment', [PaymentController::class, 'store'])->name('quick-payment.store');


Route::get('/api/bcv', function () {
    return response()->json(BncHelper::getBcvRatesCached());
});

//imports
Route::get('/import-clients', function () {
    return Inertia::render('User/ImportUsers');
})->middleware(['auth'])->name('import-clients.index');
Route::post('/import-clients', [ClientImportController::class, 'import'])->name('import-clients');


Route::get('/api/bnc/history', function (Request $request) {
    $account = $request->query('account');

    if (!$account) {
        return response()->json([
            'success' => false,
            'error' => 'Falta el número de cuenta',
            'data' => null,
        ], 422);
    }

    try {
        $data = BncHelper::getPositionHistory($account);

        return response()->json([
            'success' => true,
            'error' => null,
            'data' => $data,
        ]);
    } catch (\Throwable $e) {
        Log::error('BNC HISTORY ❌ Error al consultar historial — ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'error' => 'Error al consultar el historial',
            'data' => null,
        ]);
    }
});

Route::get('/api/banks', [PaymentController::class, 'getBanks'])->name('get-banks');

Route::get('/api/bnc/validate-reference/{reference}', [App\Http\Controllers\PaymentController::class, 'validateReference'])->middleware(['auth']);

Route::post('/api/bnc/validate-and-store-payment', [App\Http\Controllers\PaymentController::class, 'validateAndStorePayment'])->middleware(['auth']);

Route::post('/api/bnc/send-c2p', [App\Http\Controllers\PaymentController::class, 'sendC2P'])->middleware(['auth']);

Route::get('/api/users/search/{code}', [App\Http\Controllers\UserController::class, 'searchByCode']);
Route::post('/api/users/sync-wispro-all', [App\Http\Controllers\UserController::class, 'syncWisproClients'])->middleware(['auth', 'admin']);

// Rutas de prueba para BNC API
Route::get('/test-bnc/position-history', [App\Http\Controllers\BncTestController::class, 'testPositionHistory'])->name('test-bnc.position-history');
Route::get('/test-bnc/info', [App\Http\Controllers\BncTestController::class, 'testBncInfo'])->name('test-bnc.info');

Route::get('dashboard', function () {
    $user = \Illuminate\Support\Facades\Auth::user();
    $data = [];

    // Si el usuario tiene role = 0 (usuario), cargar sus pagos
    if ($user && $user->role === 0) {
        $payments = $user->payments()
            ->with(['invoice'])
            ->orderBy('id', 'desc')
            ->limit(10) // Mostrar los últimos 10 pagos
            ->get();

        // Formatear los datos igual que en PaymentController
        $formattedPayments = $payments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'amount_bs' => $payment->amount * (\App\Helpers\BncHelper::getBcvRatesCached()['Rate'] ?? 1),
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

        $data['user_payments'] = $formattedPayments;

        // Obtener contrato y plan del usuario desde Wispro si tiene id_wispro
        if ($user->id_wispro) {
            $wisproService = app(\App\Services\WisproApiService::class);

            // Obtener contrato
            $contractsResponse = $wisproService->getClientContracts($user->id_wispro);

            if ($contractsResponse['success'] && !empty($contractsResponse['data']['data'])) {
                $contractData = $contractsResponse['data']['data'][0];

                // Obtener plan
                $plan = null;
                if (!empty($contractData['plan_id'])) {
                    $planResponse = $wisproService->getPlanById($contractData['plan_id']);

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
        }
    }

    // Si el usuario es admin (role = 1), cargar estadísticas
    if ($user && $user->role === 1) {
        $wisproService = app(\App\Services\WisproApiService::class);

        // Obtener total de clientes locales
        $totalClients = \App\Models\User::where('role', 0)->count();
        $data['total_clients'] = $totalClients;

        // Obtener estadísticas de contratos por estado
        $contractStates = ['enabled', 'disabled', 'degraded', 'alerted'];
        $contractStats = [];

        foreach ($contractStates as $state) {
            $response = $wisproService->getContractsByState($state, 1, 1);
            if ($response['success'] && isset($response['data']['meta']['pagination']['total_records'])) {
                $contractStats[$state] = $response['data']['meta']['pagination']['total_records'];
            } else {
                $contractStats[$state] = 0;
            }
        }

        $data['contract_stats'] = $contractStats;

        // Obtener todos los pagos recientes para admin
        $allPayments = \App\Models\Payment::with(['user', 'invoice'])
            ->orderBy('id', 'desc')
            ->limit(50)
            ->get();

        $formattedAllPayments = $allPayments->map(function ($payment) {
            return [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'amount_bs' => $payment->amount * (\App\Helpers\BncHelper::getBcvRatesCached()['Rate'] ?? 1),
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

        $data['admin_payments'] = $formattedAllPayments;
    }

    return Inertia::render('Dashboard', $data);
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
