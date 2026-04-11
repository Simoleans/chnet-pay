<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\BdvIpg2Payment;
use App\Models\Payment;
use App\Services\BdvApiService;
use App\Services\WisproApiService;
use App\Helpers\CurrencyHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckPendingIpg2Payments extends Command
{
    protected $signature   = 'ipg2:check-pending';
    protected $description = 'Verifica pagos BDV IPG2 pendientes y los confirma si fueron aprobados';

    public function handle(BdvApiService $bdv, WisproApiService $wispro): void
    {
        $pending = BdvIpg2Payment::where('status', PaymentStatus::Pending)
            //->where('expires_at', '>', now())
            ->get();

        $this->info("Verificando {$pending->count()} pagos pendientes...");

       foreach ($pending as $ipg2Payment) {
            DB::beginTransaction();
            try {
                // Llama directo al endpoint GET /api/Payments/{paymentId}
                $check = $bdv->verifyPayment($ipg2Payment->payment_id);

                //log::info('IPG2 CRON: verifyPayment', ['check' => $check]);

                /* if ($check->status === 1 && $check->status === 1) {
                    $ipg2Payment->markApproved();
                } else {
                    $ipg2Payment->markRejected();
                } */

                // status 1 = aprobado
                 if ($check->status === 1 && $check->status === 1) {
                    $amountBs  = (float) $check->amount;
                    $amountUsd = CurrencyHelper::bsToUsd($amountBs);

                    $wisproIds = array_values(array_filter(array_map('strval', $ipg2Payment->invoice_ids)));
                    $user      = $ipg2Payment->user;

                     Payment::safeCreate(
                        $check->reference,
                        $user,
                        $wisproIds,
                        $amountUsd,
                        $check->letter . $check->number, // según respuesta del endpoint
                        'BDV-IPG2',
                        Payment::TYPE_BANK_BDV,
                        $ipg2Payment->cellphone
                    );

                    $ipg2Payment->markApproved();

                  /*   if (count($wisproIds) > 0 && $user?->id_wispro) {
                        $wispro->registerPaymentSafe(
                            $wisproIds,
                            $user->id_wispro,
                            $amountUsd,
                            "Pago IPG2 {$check->reference}",
                            $payment
                        );
                    } */

                    Log::info('IPG2 CRON: pago aprobado', [
                        'payment_id' => $ipg2Payment->payment_id,
                        'amount_bs'  => $amountBs,
                        'amount_usd' => $amountUsd,
                    ]);

                    $this->info("✅ Aprobado: {$ipg2Payment->payment_id}");

                } else {
                    Log::info('IPG2 CRON: pago aún pendiente o rechazado', [
                        'payment_id' => $ipg2Payment->payment_id,
                        'status'     => $check->status ?? 'sin status',
                    ]);

                    $this->warn("⏳ Sin confirmar: {$ipg2Payment->payment_id}");
                }

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('IPG2 CRON: Excepción', [
                    'payment_id' => $ipg2Payment->payment_id,
                    'message'    => $e->getMessage(),
                ]);
                $this->error("❌ Error en {$ipg2Payment->payment_id}: {$e->getMessage()}");
            }
        }

        // Expirar los que pasaron su tiempo límite
        /* $expired = BdvIpg2Payment::where('status', PaymentStatus::Pending)
            ->where('expires_at', '<', now())
            ->update(['status' => PaymentStatus::Expired]);

        $this->info("⌛ {$expired} pagos expirados."); */
    }
}
