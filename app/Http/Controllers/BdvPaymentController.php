<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Http\Requests\BDV\VerifyP2PRequest;
use App\Services\BdvApiService;
use App\Services\WisproApiService;
use App\Models\BcvRate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\WisproInvoiceIds;
use App\Http\Requests\BDV\StartIpg2PaymentRequest;

class BdvPaymentController extends Controller
{
    // Códigos BDV que indican un pago válido/existente
    private const BDV_CODE_OK           = 1000; // Transacción realizada (nueva)
    private const BDV_CODE_RECONCILED   = 1010; // Ya fue conciliada anteriormente

    public function verify(VerifyP2PRequest $request, BdvApiService $bdv)
    {
        $data = $request->validated();

        $telefonoPagador = $data['telefonoPagador'];
        $telefonoDestino = $data['telefonoDestino'];

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
        // 2. El banco debe responder con code 1000 o 1010 para considerar el pago válido
        $code = $resp['code'] ?? null;

       // 1010 = ya conciliado, rechazar directo
        if ($code === self::BDV_CODE_RECONCILED) {
            return response()->json([
                'success'  => false,
                'message'  => 'Este pago ya fue procesado anteriormente.',
                'bdv_code' => $code,
            ], 422);
        }

        // Solo 1000 es válido para continuar
        if ($code !== self::BDV_CODE_OK) {
            return response()->json([
                'success'  => false,
                'message'  => $resp['message'] ?? 'El banco rechazó la validación del pago.',
                'bdv_code' => $code,
            ], 422);
        }

        // 3. Evitar duplicados internos
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

        $wisproIds = [];
        if (! empty($data['invoice_ids']) && is_array($data['invoice_ids'])) {
            $wisproIds = array_values(array_filter(array_map('strval', $data['invoice_ids'])));
        } elseif (! empty($data['invoice_id'])) {
            $wisproIds = [(string) $data['invoice_id']];
        }

        // 5. Guardar el pago como verificado (el banco ya lo confirmó)
        $user    = Auth::user();
        $payment = Payment::create([
            'reference'        => $data['referencia'],
            'user_id'          => $user?->id,
            'invoice_wispro'   => WisproInvoiceIds::encode($wisproIds),
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
        if (count($wisproIds) > 0 && ! empty($data['client_id'])) {
            try {
                $wisproApiService      = new WisproApiService();
                $wisproPaymentResponse = $wisproApiService->registerPayment(
                    $wisproIds,
                    $data['client_id'],
                    now()->format('c'),
                    $amountUsd,
                    "Referencia {$data['referencia']}"
                );

                if ($wisproPaymentResponse['success']) {
                    $payment->update(['wispro_registered' => true]);
                    Log::info('BDV VERIFY P2P: Pago registrado en Wispro', [
                        'invoice_ids' => $wisproIds,
                        'client_id'  => $data['client_id'],
                        'response'   => $wisproPaymentResponse['data'],
                    ]);
                } else {
                    Log::error('BDV VERIFY P2P: Error al registrar en Wispro', [
                        'invoice_ids' => $wisproIds,
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
    public function start(StartIpg2PaymentRequest $request, BdvApiService $bdv)
    {
        $data = $request->validated();

        $payload = [
            'currency'    => 1,
            'amount'      => (string) $data['amount'],
            'reference'   => 'CHNET-' . $data['idLetter'] . $data['idNumber'] . date('YmdHis'),
            'title'       => 'Pago de Servicios CHNET',
            'description' => 'Pago de Servicios CHNET',
            'idLetter'    => $data['idLetter'],
            'idNumber'    => $data['idNumber'],
            'email'       => $data['email'],
            'cellphone'   => $data['cellphone'],
            'urlToReturn' => route('bdv.ipg2.return'),
        ];

        $resp = $bdv->createButtonPayment($payload);

        Log::info('BDV IPG2 START', ['payload' => $payload, 'resp' => $resp]);

        if (!$resp->success) {
            return back()->withErrors(['bdv' => $resp->responseMessage]);
        }

        // Guardamos en sesión para verificar al retorno
        session(['bdv_ipg2_payment_id' => $resp->paymentId]);

        return redirect()->away($resp->urlPayment);
    }

    /**
     * Retorno: aquí NO confíes solo en el retorno; consulta el estado con checkPayment.
     */
    public function retorno(Request $request, BdvApiService $bdv)
    {
        $paymentId = session('bdv_ipg2_payment_id') ?? $request->query('paymentId');

        Log::info('BDV IPG2 RETORNO', ['query_params' => $request->all(), 'session_payment_id' => $paymentId]);

        if (!$paymentId) {
            return response()->json(['error' => 'Sin paymentId'], 400);
        }

        $check = $bdv->checkButtonPayment($paymentId);

        Log::info('BDV IPG2 CHECK', ['check' => $check]);

        // Por ahora solo dump para ver qué devuelve
        return response()->json([
            'paymentId' => $paymentId,
            'check'     => $check,
        ]);
    }
}
