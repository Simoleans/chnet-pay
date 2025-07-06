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
            $response = Http::timeout(3)->get('https://pydolarve.org/api/v1/dollar?page=bcv');

            if ($response->ok()) {
                $data = $response->json();

                if (isset($data['monitors']['usd']['price']) && isset($data['monitors']['usd']['last_update'])) {
                    return [
                        'Rate' => floatval($data['monitors']['usd']['price']),
                        'Date' => $data['monitors']['usd']['last_update'],
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
        return Cache::remember('bnc_bcv_rate', now()->addMinutes(10), function () {
            $clientGuid = config('app.bnc.client_guid');

            if (!$clientGuid) {
                Log::error('BNC BCV: ClientGUID no definido');
                return null;
            }

            try {
                $response = Http::timeout(10)->post(config('app.bnc.base_url') . 'Services/BCVRates', [
                    'ClientGUID' => $clientGuid,
                    'Reference' => now()->format('YmdHis'),
                ]);

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
        });
    }

    public static function getPositionHistory(string $account): ?array
    {
        try {
            $key = self::getWorkingKey();
            $clientGuid = config('app.bnc.client_guid');
            $clientId = config('app.bnc.client_id'); // asegúrate de tener esto en tu .env

            if (!$key) throw new \Exception('WorkingKey no disponible');

            $body = [
                'ClientID' => $clientId,
                'AccountNumber' => $account,
                'ChildClientID' => '',
                'BranchID' => '',
            ];

            Log::info('BNC HISTORIAL: Enviando payload', ['client_id' => $clientId, 'account' => $account]);

            $response = BncApiService::send('Position/History', $body);

            Log::info('BNC HISTORIAL: Respuesta recibida', ['status' => $response->status()]);

            if ($response->ok() || $response->status() === 202) {
                $result = $response->json();
                $decrypted = BncCryptoHelper::decryptAES($result['value'], $key);
                Log::info('BNC HISTORIAL: Desencriptacion exitosa', ['records_count' => count($decrypted)]);
                return $decrypted;
            } else {
                Log::error('BNC HISTORIAL: Error HTTP', ['status' => $response->status()]);
            }
        } catch (\Throwable $e) {
            Log::error('BNC HISTORIAL: Excepcion', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        }

        return null;
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









}
