<?php

namespace App\Http\Controllers;

use App\Models\BcvRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BcvRateController extends Controller
{
    /**
     * Obtener la última tasa BCV
     */
    public function getLatest()
    {
        $latest = BcvRate::getLatestRate();

        if (!$latest) {
            return response()->json([
                'Rate' => null,
                'Date' => null,
                'message' => 'No hay tasa BCV registrada'
            ], 404);
        }

        return response()->json($latest);
    }

    /**
     * Guardar una nueva tasa BCV
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'rate' => 'required|numeric|min:0',
            'date' => 'nullable|date',
        ]);

        try {
            $bcvRate = BcvRate::create([
                'rate' => $validated['rate'],
                'date' => $validated['date'] ?? now()->toDateString(),
                'updated_by' => auth()->id(),
            ]);

            // Limpiar el caché para forzar la actualización
            Cache::forget('bnc_bcv_rate');

            Log::info('BCV actualizado manualmente', [
                'rate' => $bcvRate->rate,
                'date' => $bcvRate->date,
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tasa BCV actualizada correctamente',
                'data' => [
                    'Rate' => floatval($bcvRate->rate),
                    'Date' => $bcvRate->date->format('Y-m-d'),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al guardar BCV', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la tasa BCV',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el historial de tasas BCV
     */
    public function history(Request $request)
    {
        $limit = $request->get('limit', 10);

        $history = BcvRate::latest('created_at')
            ->take($limit)
            ->get()
            ->map(function ($rate) {
                return [
                    'id' => $rate->id,
                    'rate' => floatval($rate->rate),
                    'date' => $rate->date->format('Y-m-d'),
                    'created_at' => $rate->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json($history);
    }
}
