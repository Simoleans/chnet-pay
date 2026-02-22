<?php


namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Services\Ipg2BdvClient;

class BdvApiService
{

    public function __construct(
        private readonly Ipg2BdvClient $ipg2
    ) {}
    public function verifyP2P(string $cedulaPagador,string $telefonoPagador,string $telefonoDestino,string $referencia,string $fechaPago,string $importe,string $bancoOrigen,bool $reqCed = false) : array
    {
        $body = [
            "cedulaPagador"   => $cedulaPagador,
            "telefonoPagador" => $telefonoPagador,
            "telefonoDestino" => $telefonoDestino,
            "referencia"      => $referencia,
            "fechaPago"       => $fechaPago,
            "importe"         => $importe,
            "bancoOrigen"     => $bancoOrigen,
            "reqCed"          => $reqCed,
        ];

        $response = Http::withApiHeadersBDV()
            ->timeout(20)
            ->post(config('app.bdv.base_url') . '/getMovement', $body);

        return $response->json();
    }

    public function sendC2PClientBDV(string $customerDocumentId, string $customerNumberInstrument) : array
    {

        $body = [
            "customerDocumentId" => $customerDocumentId,
            "customerNumberInstrument" => $customerNumberInstrument,
        ];
        $response = Http::withApiHeadersBDV()
            ->timeout(20)
            ->post(config('app.bdv.base_url') . '/BankMobilePaymentC2P/paymentkey', $body);

        return $response->json();

    }

    public function verifyC2P(string $customerDocumentId, string $customerNumberInstrument, string $amount, string $customerBankCode, string $concept, string $otp, string $coinType, string $operationType = 'CELE') : array
    {

        $body = [
            "customerDocumentId" => $customerDocumentId,
            "customerNumberInstrument" => $customerNumberInstrument,
            "amount" => $amount,
            "customerBankCode" => $customerBankCode,
            "concept" => $concept,
            "otp" => $otp,
            "coinType" => $coinType,
            "operationType" => $operationType,
        ];

        $response = Http::withApiHeadersBDV()
            ->timeout(20)
            ->post(config('app.bdv.base_url') . '/BankMobilePaymentC2P/process', $body);

        return $response->json();

    }

    /**
     * Crea pago y devuelve: paymentId + urlPayment (para redirigir)
     */
    public function createButtonPayment(array $payload)
    {
        return $this->ipg2->createPayment($payload);
    }

    /**
     * Consulta estado real del pago (para confirmar)
     */
    public function checkButtonPayment(string $paymentId)
    {
        return $this->ipg2->checkPayment($paymentId);
    }
}
