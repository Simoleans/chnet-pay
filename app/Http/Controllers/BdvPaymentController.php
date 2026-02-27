<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\BdvApiService;
use App\Models\Invoice;
use App\Models\Payment;
class BdvPaymentController extends Controller
{
    public function verify(Request $request, BdvApiService $bdv)
    {
        $data = $request->validate([
            'cedulaPagador'   => ['required','string'],
            'telefonoPagador' => ['required','string'],
            'telefonoDestino' => ['required','string'],
            'referencia'      => ['required','string'],
            'fechaPago'       => ['required','string'], // ideal: date_format
            'importe'         => ['required','string'],
            'bancoOrigen'     => ['required','string'],
            'reqCed'          => ['sometimes','boolean'],
        ]);

        $resp = $bdv->verifyP2P(
            $data['cedulaPagador'],
            $data['telefonoPagador'],
            $data['telefonoDestino'],
            $data['referencia'],
            $data['fechaPago'],
            $data['importe'],
            $data['bancoOrigen'],
            $data['reqCed'] ?? false
        );

        return response()->json($resp);
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
