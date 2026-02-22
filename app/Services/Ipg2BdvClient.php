<?php

namespace App\Services;

class Ipg2BdvClient
{
    public function __construct()
    {
        require_once app_path('Services/Ipg2/ipg2-bdv.php');
    }

    public function createPayment(array $data)
    {
        $ipg = new \IpgBdv2(
            config('app.ipg2.client_id'),
            config('app.ipg2.client_secret')
        );

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

        return $ipg->createPayment($req);
    }

    public function checkPayment(string $paymentId)
    {
        $ipg = new \IpgBdv2(
            config('app.ipg2.client_id'),
            config('app.ipg2.client_secret')
        );

        return $ipg->checkPayment($paymentId);
    }
}
