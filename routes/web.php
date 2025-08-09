<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
//use App\Http\Controllers\TestApiController;
use App\Http\Controllers\{PlanController,UserController,ZoneController,PaymentController,ClientImportController};
use App\Helpers\BncHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
/* use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
 */
Route::get('/', function () {
    return Inertia::render('auth/Login');
})->name('home');

//Route::post('pay-fee', [TestApiController::class, 'testApi'])->name('pay-fee');

//plans
Route::resource('plans', PlanController::class);

//users
Route::resource('users', UserController::class);

//zones
Route::resource('zones', ZoneController::class);

//payments
Route::resource('payments', PaymentController::class)->middleware('auth');
Route::patch('/payments/{payment}/verify', [PaymentController::class, 'toggleVerification'])->name('payments.toggle-verification')->middleware('auth');
Route::get('/payments-export', [PaymentController::class, 'export'])->name('payments.export')->middleware('auth');

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
    }

    return Inertia::render('Dashboard', $data);
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
