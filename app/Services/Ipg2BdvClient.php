<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Ipg2BdvClient
{
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;

    public function __construct()
    {
        require_once app_path('Services/Ipg2/ipg2-bdv.php');

        /* $this->ipg = new \IpgBdv2(
            config('app.ipg2.client_id'),
            config('app.ipg2.client_secret')
        ); */

        $this->clientId = config('app.ipg2.client_id');
        $this->clientSecret = config('app.ipg2.client_secret');
        $this->baseUrl = config('app.ipg2.base_url');
    }

    public function createPayment(array $data)
    {
        $req = new \IpgBdvPaymentRequest();

        $req->currency     = $data['currency'];      // 1 = Bs
        $req->amount       = $data['amount'];
        $req->reference    = $data['reference'];
        $req->title        = $data['title'];
        $req->description  = $data['description'];
        $req->idLetter     = $data['idLetter'];      // V/E
        $req->idNumber     = $data['idNumber'];
        $req->email        = $data['email'];
        $req->cellphone    = $data['cellphone'];
        $req->urlToReturn  = $data['urlToReturn'];

        // Si aplica jurídico (opcional)
        if (!empty($data['rifLetter']) && !empty($data['rifNumber'])) {
            $req->rifLetter = $data['rifLetter'];
            $req->rifNumber = $data['rifNumber'];
        }

        $ipg = new \IpgBdv2($this->clientId, $this->clientSecret);
        return $ipg->createPayment($req);
    }

    public function checkPayment(string $paymentId)
    {
        $ipg = new \IpgBdv2($this->clientId, $this->clientSecret);
        return $ipg->checkPayment($paymentId);
    }

    private function getAccessToken(): string
    {
        return Cache::remember('ipg2_access_token', 3000, function () {
            $response = Http::asForm()->post("{$this->baseUrl}/connect/token", [
                'grant_type'    => 'client_credentials',
                'client_id'     => $this->clientId,
                'client_secret' => $this->clientSecret,
            ]);

            Log::info('IPG2: Access token response', ['response' => $response->json()]);

            if (!$response->successful()) {
                Log::error('IPG2: Error obteniendo token', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new \Exception('IPG2: No se pudo obtener el access token');
            }

            return $response->json('access_token');
        });
    }

    public function verifyPayment(string $paymentId): object
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/api/Payments/{$paymentId}");

        if (!$response->successful()) {
            throw new \Exception("IPG2: Error verificando pago {$paymentId}: " . $response->body());
        }

        return (object) $response->json();
    }
}
