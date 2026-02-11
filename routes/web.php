<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
//use App\Http\Controllers\TestApiController;
use App\Http\Controllers\{PlanController,UserController,ZoneController,PaymentController,ClientImportController,PublicPaymentController,DashboardController};
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

//payments - index, show, edit, update, destroy solo para administradores
Route::resource('payments', PaymentController::class)
    ->except(['store'])
    ->middleware(['auth', 'admin']);

// Permite a todos los usuarios autenticados guardar pagos
Route::post('/payments', [PaymentController::class, 'store'])
    ->middleware(['auth'])
    ->name('payments.store');

Route::patch('/payments/{payment}/verify', [PaymentController::class, 'toggleVerification'])->middleware(['auth', 'admin'])->name('payments.toggle-verification');
Route::get('/payments-export', [PaymentController::class, 'export'])->middleware(['auth', 'admin'])->name('payments.export');

// Ruta especial para pago rápido desde login (sin autenticación)
Route::post('/quick-payment', [PaymentController::class, 'store'])->name('quick-payment.store');


Route::get('/api/bcv', function () {
    return response()->json(BncHelper::getBcvRatesCached());
});

// Rutas para gestión manual de BCV (solo admin)
Route::post('/api/bcv/store', [App\Http\Controllers\BcvRateController::class, 'store'])->middleware(['auth', 'admin']);
Route::get('/api/bcv/history', [App\Http\Controllers\BcvRateController::class, 'history'])->middleware(['auth', 'admin']);

//imports
Route::get('/import-clients', function () {
    return Inertia::render('User/ImportUsers');
})->middleware(['auth'])->name('import-clients.index');
Route::post('/import-clients', [ClientImportController::class, 'import'])->name('import-clients');


Route::get('/api/bnc/history', function (Request $request) {
    //$account = $request->query('account');
    $account = config('app.bnc.account');

    if (!$account) {
        return response()->json([
            'success' => false,
            'error' => 'Falta el número de cuenta',
            'data' => null,
        ], 422);
    }

    try {
        $data = BncHelper::getPositionHistory(null, $account);

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

Route::get('dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
