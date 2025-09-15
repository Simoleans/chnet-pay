<?php

namespace App\Http\Controllers;

use App\Helpers\BncHelper;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BncTestController extends Controller
{
    /**
     * Prueba el endpoint Position/History de BNC
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function testPositionHistory(Request $request): JsonResponse
    {
        try {
            // Obtener parámetros opcionales del request
            $clientId = $request->get('client_id');
            $accountNumber = $request->get('account_number');
            $childClientID = $request->get('child_client_id', '');
            $branchID = $request->get('branch_id', '');

            // Llamar al método del helper
            $result = BncHelper::getPositionHistory(
                $clientId,
                $accountNumber,
                $childClientID,
                $branchID
            );

            if ($result === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al obtener historial de posiciones',
                    'data' => null,
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Historial obtenido exitosamente',
                'data' => $result,
                'count' => is_array($result) ? count($result) : 0,
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Excepción al obtener historial: ' . $e->getMessage(),
                'data' => null,
                'error' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ], 500);
        }
    }

    /**
     * Prueba otros métodos de BNC para depuración
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function testBncInfo(Request $request): JsonResponse
    {
        try {
            $workingKey = BncHelper::getWorkingKey();
            $bcvRate = BncHelper::getBcvRatesCached();

            return response()->json([
                'success' => true,
                'data' => [
                    'working_key_available' => !empty($workingKey),
                    'working_key_length' => $workingKey ? strlen($workingKey) : 0,
                    'bcv_rate' => $bcvRate,
                    'config' => [
                        'client_id' => config('app.bnc.client_id'),
                        'account' => config('app.bnc.account'),
                        'base_url' => config('app.bnc.base_url'),
                        'client_guid' => config('app.bnc.client_guid') ? 'configured' : 'not_configured',
                        'master_key' => config('app.bnc.master_key') ? 'configured' : 'not_configured',
                    ]
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información de BNC: ' . $e->getMessage(),
                'error' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            ], 500);
        }
    }
}
