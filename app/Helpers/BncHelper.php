<?php

namespace App\Helpers;

use App\Models\ApiStatus;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\BncApiService;
use App\Helpers\BncLogger;

class BncHelper
{
    public static function getWorkingKey(): ?string
    {
        $record = ApiStatus::where('api_name', 'bnc')
            ->whereDate('generated_at', now()->toDateString())
            ->latest()
            ->first();

        if (!$record) {
            Artisan::call('bnc:refresh-working-key');
            $record = ApiStatus::where('api_name', 'bnc')->latest()->first();
        }

        return $record?->working_key;
    }

    public static function getBcvRatesCached(): ?array
    {
       /*  $fallback = self::getBcvRateFromBNC();
        return $fallback; */
        return Cache::remember('bnc_bcv_rate', now()->addMinutes(10), function () {
            // Intentar obtener la tasa desde el endpoint de pydolarve
            $primary = self::getBcvRateFromPydolarve();
            if ($primary) return $primary;

            // Si falla, intentar obtener la tasa desde el endpoint de bnc
            $fallback = self::getBcvRateFromBNC();
            if ($fallback) return $fallback;



            // Si ambos fallan, retornar null
            return null;
        });
    }

    private static function getBcvRateFromPydolarve(): ?array
    {
        try {
            $response = Http::timeout(3)->get('https://ve.dolarapi.com/v1/dolares/oficial');

            if ($response->ok()) {
                $data = $response->json();

                if (isset($data['promedio']) && isset($data['fechaActualizacion'])) {
                    return [
                        'Rate' => floatval($data['promedio']),
                        'Date' => $data['fechaActualizacion'],
                        'source' => 'pydolarve',
                    ];
                } else {
                    Log::error('BCV PYDOLARVE: Estructura inesperada', ['data' => $data]);
                }
            } else {
                Log::error('BCV PYDOLARVE: HTTP Status invalido', ['status' => $response->status()]);
            }
        } catch (\Throwable $e) {
            Log::error('BCV PYDOLARVE: Error de conexion', ['message' => $e->getMessage()]);
        }

        return null;
    }



    public static function getBcvRateFromBNC(): ?array
    {
        try {
            $key = self::getWorkingKey();
            $clientId = config('app.bnc.client_id');


            $body = [
                'ClientID' => $clientId,
                'ChildClientID' => '',
                'BranchID' => '',
            ];

            Log::info('BNC BCV: Enviando solicitud', ['client_id' => $clientId]);

            $response = BncApiService::send('Services/BCVRates', $body);

            //Log::info('BNC BCV: Respuesta recibida', ['status' => $response->status()]);

            if (in_array($response->status(), [200, 202])) {
                $json = $response->json();

                if (!isset($json['value'])) {
                    Log::error('BNC BCV: Respuesta sin campo value');
                    return null;
                }

                $decrypted = BncCryptoHelper::decryptAES($json['value'], $key);

                //Log::info('BNC BCV: Desencriptación exitosa', ['data' => $decrypted]);

                if (isset($decrypted['PriceRateBCV']) && isset($decrypted['dtRate'])) {
                    return [
                        'Rate' => floatval($decrypted['PriceRateBCV']),
                        'Date' => $decrypted['dtRate'],
                        'source' => 'bnc',
                    ];
                } else {
                    Log::error('BNC BCV: Estructura inesperada en respuesta desencriptada', ['data' => $decrypted]);
                }
            } else {
                Log::error('BNC BCV: Error HTTP', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('BNC BCV: Excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        return null;
        /* return Cache::remember('bnc_bcv_rate', now()->addMinutes(10), function () {
            $clientGuid = config('app.bnc.client_guid');


            if (!$clientGuid) {
                Log::error('BNC BCV: ClientGUID no definido');
                return null;
            }

            Log::info('BNC BCV: ClientGUID', ['base_url' => config('app.bnc.base_url')]);

            try {
                $response = Http::timeout(10)->post(config('app.bnc.base_url') . 'Services/BCVRates');

                if ($response->ok() && $response['status'] === 'OK') {
                    return [
                        'Rate' => floatval($response['value']['PriceRateBCV']),
                        'Date' => $response['value']['dtRate'],
                        'source' => 'bnc',
                    ];

                }

                Log::error('BNC BCV: Respuesta no OK', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            } catch (\Throwable $e) {
                Log::error('BNC BCV: Fallo de conexion', ['message' => $e->getMessage()]);
            }

            return null;
        }); */
    }

    public static function validateOperationReference(string $reference, string $dateMovement, float $expectedAmount): ?array
    {
        try {
            $key = self::getWorkingKey();
            $clientId = config('app.bnc.client_id');
            $account = config('app.bnc.account');

            $body = array_filter([
                'ClientID' => $clientId,
                'AccountNumber' => $account,
                'Reference' => $reference,
                'Amount' => $expectedAmount,
                'DateMovement' => $dateMovement,
                'ChildClientID' => '',
                'BranchID' => '',
            ], fn($v) => !is_null($v));

            Log::info('BNC VALIDACION REF: Enviando validacion', [
                'reference' => $reference,
                'amount' => $expectedAmount,
                'date' => $dateMovement
            ]);

            $response = BncApiService::send('Position/Validate', $body);

            if (in_array($response->status(), [200, 202])) {
                $json = $response->json();

                if (!isset($json['value'])) {
                    Log::error('BNC VALIDACION REF: Respuesta sin campo value');
                    return null;
                }

                $decrypted = BncCryptoHelper::decryptAES($json['value'], $key);
                Log::info('BNC VALIDACION REF: Validacion exitosa', ['result' => $decrypted]);

                return $decrypted;
            }

            Log::error('BNC VALIDACION REF: Error HTTP', ['status' => $response->status()]);
        } catch (\Throwable $e) {
            Log::error('BNC VALIDACION REF: Excepcion', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        return null;
    }

    public static function getBanks(): ?array
    {
        try {
            Log::info('BNC BANCOS: Iniciando getBanks');

            // Paso 1: Obtener WorkingKey
            $key = self::getWorkingKey();

            if (!$key) {
                Log::error('BNC BANCOS: WorkingKey no disponible');
                throw new \Exception('WorkingKey no disponible');
            }

            // Paso 2: Obtener configuraciones
            $clientId = config('app.bnc.client_id');
            $baseUrl = config('app.bnc.base_url');

            if (empty($clientId)) {
                Log::error('BNC BANCOS: BNC_CLIENT_ID no configurado');
                throw new \Exception('BNC_CLIENT_ID no está configurado');
            }

            // Paso 3: Preparar payload
            $body = [
                'ClientID' => $clientId,
                'ChildClientID' => '',
                'BranchID' => '',
            ];

            Log::info('BNC BANCOS: Payload preparado', ['client_id' => $clientId]);

            // Paso 4: Verificar dependencias de cifrado
            if (!class_exists('App\Helpers\BncCryptoHelper')) {
                Log::error('BNC BANCOS: BncCryptoHelper no encontrado');
                throw new \Exception('BncCryptoHelper no encontrado');
            }

            if (!class_exists('phpseclib3\Crypt\AES')) {
                Log::error('BNC BANCOS: phpseclib3 no instalado');
                throw new \Exception('phpseclib3 no está instalado - ejecutar composer install');
            }

            // Paso 5: Enviar petición
            Log::info('BNC BANCOS: Enviando peticion a BNC API');
            $response = BncApiService::send('Services/Banks', $body);

            Log::info('BNC BANCOS: Respuesta recibida', ['status' => $response->status()]);

            // Paso 6: Procesar respuesta
            if ($response->ok() || $response->status() === 202) {
                $result = $response->json();

                if (!isset($result['value'])) {
                    Log::error('BNC BANCOS: Respuesta sin campo value');
                    return null;
                }

                // Paso 7: Desencriptar
                $decrypted = BncCryptoHelper::decryptAES($result['value'], $key);

                Log::info('BNC BANCOS: Desencriptacion exitosa', ['bancos_count' => count($decrypted)]);
                return $decrypted;
            } else {
                Log::error('BNC BANCOS: Error HTTP', ['status' => $response->status()]);
            }

        } catch (\Throwable $e) {
            Log::error('BNC BANCOS: Excepcion', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        return null;
    }

/**
     * Envía un pago C2P (Comercio a Persona) al endpoint MobPayment/SendC2P
     *
     * @param int $debtorBankCode      Código del banco emisor (por ejemplo, 191)
     * @param string $debtorCellPhone  Teléfono móvil del emisor (formato internacional sin "+", ej: 584241234567)
     * @param string $debtorID         Cédula o RIF del emisor (ej: V12345678)
     * @param float $amount            Monto del pago
     * @param string $token            Token de validación enviado por el banco
     * @param string $terminal         ID del terminal autorizado
     * @param string $childClientID    (Opcional)
     * @param string $branchID         (Opcional)
     * @return array|null              Respuesta desencriptada o null si falla
     */
    public static function sendC2PPayment(
        int $debtorBankCode,
        string $debtorCellPhone,
        string $debtorID,
        float $amount,
        string $token,
        string $terminal,
        string $childClientID = '',
        string $branchID = ''
    ): ?array {
        try {
            $key = BncHelper::getWorkingKey();

            $body = [
                'DebtorBankCode'  => $debtorBankCode,
                'DebtorCellPhone' => $debtorCellPhone,
                'DebtorID'        => $debtorID,
                'Amount'          => $amount,
                'Token'           => $token,
                'Terminal'        => $terminal,
                'ChildClientID'   => $childClientID,
                'BranchID'        => $branchID,
            ];



            $response = BncApiService::send('MobPayment/SendC2P', $body);

            Log::info('BNC C2P: Enviando pago', ['payload' => $body,'url' => 'MobPayment/SendC2P']);

            if (in_array($response->status(), [200, 202])) {
                $json = $response->json();

                if (!isset($json['value'])) {
                    Log::error('BNC C2P: Respuesta sin campo value');
                    return null;
                }

                $decrypted = BncCryptoHelper::decryptAES($json['value'], $key);
                Log::info('BNC C2P: Pago exitoso', ['result' => $decrypted]);

                return $decrypted;
            }

            // Log detallado del error
            $rawBody = $response->body();
            $json = null;
            $decryptedError = null;
            try {
                $json = $response->json();
                if (is_array($json) && isset($json['value'])) {
                    $decryptedError = BncCryptoHelper::decryptAES($json['value'], $key);
                }
            } catch (\Throwable $e) {
                // ignorar errores de parseo
            }

            Log::error('BNC C2P: Error HTTP', [
                'status' => $response->status(),
                'body' => $rawBody,
                'json' => $json,
                'decrypted' => $decryptedError,
            ]);

            // Retornar estructura de error para que el controlador pueda propagar el mensaje
            return [
                'error' => true,
                'status' => $response->status(),
                'message' => is_array($json) && isset($json['message']) ? $json['message'] : 'Error procesando C2P',
                'decrypted' => $decryptedError,
            ];
        } catch (\Throwable $e) {
            Log::error('BNC C2P: Excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return [
                'error' => true,
                'status' => 500,
                'message' => 'Excepción al enviar C2P: ' . $e->getMessage(),
                'decrypted' => null,
            ];
        }

        return null;
    }












    /**
     * Obtiene el historial de posiciones de una cuenta
     *
     * @param string|null $clientId     ID del cliente (opcional, usa el de configuración si no se proporciona)
     * @param string|null $accountNumber Número de cuenta (opcional, usa el de configuración si no se proporciona)
     * @param string $childClientID     (Opcional)
     * @param string $branchID          (Opcional)
     * @return array|null               Respuesta desencriptada o null si falla
     */
    public static function getPositionHistory(
        ?string $clientId = null,
        ?string $accountNumber = null,
        string $childClientID = '',
        string $branchID = ''
    ): ?array {
        try {
            $key = self::getWorkingKey();
            $clientId = $clientId ?? config('app.bnc.client_id');
            $accountNumber = $accountNumber ?? config('app.bnc.account');

            if (!$clientId || !$accountNumber) {
                Log::error('BNC POSITION HISTORY: ClientID o AccountNumber no disponibles');
                return null;
            }

            $body = [
                'ClientID' => $clientId,
                'AccountNumber' => $accountNumber,
                'ChildClientID' => $childClientID,
                'BranchID' => $branchID,
            ];

            Log::info('BNC POSITION HISTORY: Enviando solicitud', [
                'client_id' => $clientId,
                'account' => $accountNumber
            ]);

            $response = BncApiService::send('Position/History', $body);

            if (in_array($response->status(), [200, 202])) {
                $json = $response->json();

                if (!isset($json['value'])) {
                    Log::error('BNC POSITION HISTORY: Respuesta sin campo value');
                    return null;
                }

                $decrypted = BncCryptoHelper::decryptAES($json['value'], $key);
                Log::info('BNC POSITION HISTORY: Consulta exitosa', ['data_count' => is_array($decrypted) ? count($decrypted) : 'no_array']);

                return $decrypted;
            }

            Log::error('BNC POSITION HISTORY: Error HTTP', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        } catch (\Throwable $e) {
            Log::error('BNC POSITION HISTORY: Excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        return null;
    }

}
