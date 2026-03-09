<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\BdvApiService;
use App\Services\WisproApiService;
use App\Models\BcvRate;
use App\Models\Invoice;
use App\Models\Payment;

class BdvPaymentController extends Controller
{
    // Códigos BDV que indican un pago válido/existente
    private const BDV_CODE_OK           = 1000; // Transacción realizada (nueva)
    private const BDV_CODE_RECONCILED   = 1010; // Ya fue conciliada anteriormente

    public function verify(Request $request, BdvApiService $bdv)
    {
        $data = $request->validate([
            'cedulaPagador'   => ['required', 'string'],
            'telefonoPagador' => ['required', 'string'],
            'telefonoDestino' => ['required', 'string'],
            'referencia'      => ['required', 'string'],
            'fechaPago'       => ['required', 'string'],
            'importe'         => ['required', 'string'],
            'bancoOrigen'     => ['required', 'string'],
            'reqCed'          => ['sometimes', 'boolean'],
            'invoice_id'      => ['sometimes', 'nullable', 'string'],
            'client_id'       => ['sometimes', 'nullable', 'string'],
        ]);

        // Normalizar teléfonos: eliminar +58 y dejar formato 04XXXXXXXXX
        $normalizarTelefono = fn(string $tlf): string =>
            preg_replace('/^\+?58/', '0', preg_replace('/\D/', '', $tlf));

        $telefonoPagador = $normalizarTelefono($data['telefonoPagador']);
        $telefonoDestino = $normalizarTelefono($data['telefonoDestino']);

        // 1. Consultar al BDV
        $resp = $bdv->verifyP2P(
            $data['cedulaPagador'],
            $telefonoPagador,
            $telefonoDestino,
            $data['referencia'],
            $data['fechaPago'],
            $data['importe'],
            $data['bancoOrigen'],
            $data['reqCed'] ?? false
        );

        Log::info('BDV VERIFY P2P: Respuesta del banco', ['response' => $resp]);

        // 2. El banco debe responder con code 1000 o 1010 para considerar el pago válido
        $code = $resp['code'] ?? null;

        if (!in_array($code, [self::BDV_CODE_OK, self::BDV_CODE_RECONCILED])) {
            return response()->json([
                'success' => false,
                'message' => $resp['message'] ?? 'El banco rechazó la validación del pago.',
                'bdv_code' => $code,
            ], 422);
        }

        // 3. Evitar duplicados en nuestra BD (el banco ya confirma si fue conciliado vía 1010)
        if (Payment::where('reference', $data['referencia'])->exists()) {
            return response()->json([
                'success'            => true,
                'already_registered' => true,
                'message'            => 'El pago ya fue registrado anteriormente en el sistema.',
                'bdv_code'           => $code,
            ]);
        }

        // 4. Obtener tasa BCV y convertir Bs → USD
        $bcvData = BcvRate::getLatestRate();
        $bcvRate = $bcvData['Rate'] ?? null;

        if (!$bcvRate) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo obtener la tasa BCV. Intente nuevamente.',
            ], 500);
        }

        $amountBs  = (float) $data['importe'];
        $amountUsd = $amountBs / $bcvRate;

        // 5. Guardar el pago como verificado (el banco ya lo confirmó)
        $user    = Auth::user();
        $payment = Payment::create([
            'reference'        => $data['referencia'],
            'user_id'          => $user?->id,
            'invoice_wispro'   => $data['invoice_id'] ?? null,
            'amount'           => $amountUsd,
            'id_number'        => $data['cedulaPagador'],
            'bank'             => $data['bancoOrigen'],
            'phone'            => $data['telefonoPagador'],
            'payment_date'     => $data['fechaPago'],
            'verify_payments'  => true,
            'wispro_registered' => false,
            'type_bank'        => Payment::TYPE_BANK_BDV,
        ]);

        // 6. Registrar en Wispro si vienen los datos necesarios
        if (!empty($data['invoice_id']) && !empty($data['client_id'])) {
            try {
                $wisproApiService      = new WisproApiService();
                $wisproPaymentResponse = $wisproApiService->registerPayment(
                    [$data['invoice_id']],
                    $data['client_id'],
                    now()->format('c'),
                    $amountUsd,
                    "Referencia {$data['referencia']}"
                );

                if ($wisproPaymentResponse['success']) {
                    $payment->update(['wispro_registered' => true]);
                    Log::info('BDV VERIFY P2P: Pago registrado en Wispro', [
                        'invoice_id' => $data['invoice_id'],
                        'client_id'  => $data['client_id'],
                        'response'   => $wisproPaymentResponse['data'],
                    ]);
                } else {
                    Log::error('BDV VERIFY P2P: Error al registrar en Wispro', [
                        'invoice_id' => $data['invoice_id'],
                        'client_id'  => $data['client_id'],
                        'error'      => $wisproPaymentResponse['error'] ?? 'Error desconocido',
                        'message'    => $wisproPaymentResponse['message'] ?? null,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('BDV VERIFY P2P: Excepción al registrar en Wispro', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success'  => true,
            'message'  => $resp['message'] ?? 'Pago verificado y registrado exitosamente.',
            'bdv_code' => $code,
            'data'     => [
                'payment_id'   => $payment->id,
                'amount_bs'    => $amountBs,
                'amount_usd'   => $amountUsd,
                'verified'     => true,
                'bdv_response' => $resp['data'] ?? null,
            ],
        ]);
    }

    public function sendOtp(Request $request, BdvApiService $bdv)
    {
        $data = $request->validate([
            'customerDocumentId'       => ['required','string'],
            'customerNumberInstrument' => ['required','string'], // teléfono o instrumento según BDV
        ]);

        $resp = $bdv->sendC2PClientBDV(
            $data['customerDocumentId'],
            $data['customerNumberInstrument'],
        );

        return response()->json($resp);
    }

    /**
     * Paso 2: procesar C2P con OTP
     * Lo llamas cuando el usuario escribe el código y confirma
     */
    public function process(Request $request, BdvApiService $bdv)
    {
        $data = $request->validate([
            'customerDocumentId'       => ['required','string'],
            'customerNumberInstrument' => ['required','string'],
            'amount'                   => ['required','string'], // si manejas decimal, usa regex
            'customerBankCode'         => ['required','string'],
            'concept'                  => ['required','string'],
            'otp'                      => ['required','string'],
            'coinType'                 => ['required','string'], // según BDV (ej: VES)
            'operationType'            => ['sometimes','string'], // default CELE
        ]);

        $resp = $bdv->verifyC2P(
            $data['customerDocumentId'],
            $data['customerNumberInstrument'],
            $data['amount'],
            $data['customerBankCode'],
            $data['concept'],
            $data['otp'],
            $data['coinType'],
            $data['operationType'] ?? 'CELE'
        );

        return response()->json($resp);
    }

     /**
     * Crea el pago en IPG2 y redirige al usuario a urlPayment.
     * Ejemplo: pagar una factura de internet.
     */
    public function start(Request $request, BdvApiService $bdv)
    {
        $data = $request->validate([
            'invoice_id' => ['required','integer'],
        ]);

        // 1) Busca la factura
        $invoice = Invoice::findOrFail($data['invoice_id']);

        // 2) Arma payload IPG2 (ajusta campos según tu ipg2-bdv.php)
        $payload = [
            'currency'    => 1,
            'amount'      => (string) $invoice->amount,
            'reference'   => 'INTERNET-' . $invoice->id,
            'title'       => 'Pago de Internet',
            'description' => 'Factura #' . $invoice->id,

            // pagador (ejemplo usando usuario logueado)
            'idLetter'  => 'V',
            'idNumber'  => (string) $request->user()->cedula,
            'email'     => (string) $request->user()->email,
            'cellphone' => (string) $request->user()->telefono,

            // tu URL de retorno
            'urlToReturn' => route('bdv.ipg2.return'),
        ];

        $resp = $bdv->createButtonPayment($payload);

        // OJO: si resp es objeto, aquí ajustas ($resp->success)
        $success = is_array($resp) ? ($resp['success'] ?? false) : ($resp->success ?? false);

        if (!$success) {
            $msg = is_array($resp) ? ($resp['responseMessage'] ?? 'Error IPG2') : ($resp->responseMessage ?? 'Error IPG2');
            return back()->withErrors(['bdv' => $msg]);
        }

        $paymentId  = is_array($resp) ? $resp['paymentId'] : $resp->paymentId;
        $urlPayment = is_array($resp) ? $resp['urlPayment'] : $resp->urlPayment;

        // 3) Guardas paymentId en tu BD como PENDING
        Payment::create([
            'invoice_id' => $invoice->id,
            'payment_id' => $paymentId,
            'reference'  => 'INTERNET-' . $invoice->id,
            'status'     => 'PENDING',
        ]);

        // 4) REDIRECCIÓN a la pasarela
        return redirect()->away($urlPayment);
    }

    /**
     * Retorno: aquí NO confíes solo en el retorno; consulta el estado con checkPayment.
     */
    public function retorno(Request $request, BdvApiService $bdv)
    {
        // depende de cómo lo envíe Biopago/IPG2:
        $paymentId = $request->query('paymentId');

        if (!$paymentId) {
            return redirect('/')->withErrors(['bdv' => 'Retorno sin paymentId']);
        }

        $check = $bdv->checkButtonPayment($paymentId);

        $success = is_array($check) ? ($check['success'] ?? false) : ($check->success ?? false);

        $local = Payment::where('payment_id', $paymentId)->first();

        if (!$local) {
            return redirect('/')->withErrors(['bdv' => 'Pago no encontrado en sistema']);
        }

        if (!$success) {
            $local->update(['status' => 'ERROR', 'raw' => json_encode($check)]);
            $msg = is_array($check) ? ($check['responseMessage'] ?? 'Error verificando pago') : ($check->responseMessage ?? 'Error verificando pago');
            return redirect()->route('internet.invoice.show', $local->invoice_id)
                ->withErrors(['bdv' => $msg]);
        }

        $status = is_array($check) ? ($check['status'] ?? null) : ($check->status ?? null);

        // ✅ Ajusta estos estados a lo que realmente devuelva tu IPG2
        $approved = in_array($status, ['APPROVED','COMPLETED','PAID','SUCCESS'], true);

        if ($approved) {
            $local->update(['status' => 'PAID', 'raw' => json_encode($check)]);
            $local->invoice->markAsPaid(); // tu método
            return redirect()->route('internet.invoice.show', $local->invoice_id)
                ->with('ok', 'Pago aprobado');
        }

        $local->update(['status' => $status ?? 'UNKNOWN', 'raw' => json_encode($check)]);
        return redirect()->route('internet.invoice.show', $local->invoice_id)
            ->withErrors(['bdv' => 'Pago no aprobado. Estado: '.($status ?? 'desconocido')]);
    }
}
