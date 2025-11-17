<?php

namespace App\Jobs;

use App\Services\WisproApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncWisproClients implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 5;
    public $timeout = 900;

    protected $perPage = 115;

    public function __construct()
    {
        //
    }

    public function handle(WisproApiService $wisproApiService): void
    {
        Log::info("🚀 Iniciando ORQUESTADOR de sincronización Wispro");

        try {
            // 1. Obtener primera página para conocer total
            $response = $wisproApiService->getClients(1, $this->perPage);

            if (!$response['success']) {
                throw new \Exception('Error al obtener clientes de Wispro: ' . ($response['error'] ?? 'Error desconocido'));
            }

            $data         = $response['data'];
            $totalPages   = $data['meta']['pagination']['total_pages']   ?? 1;
            $totalRecords = $data['meta']['pagination']['total_records'] ?? 0;

            Log::info("📊 Se encolarán {$totalPages} jobs (páginas) para {$totalRecords} registros");

            // 2. Despachar un job por página
            for ($page = 1; $page <= $totalPages; $page++) {
                dispatch(new SyncWisproClientsPage($page, $this->perPage));
            }

            Log::info("✅ Orquestador completado. Jobs por página encolados correctamente.");
        } catch (\Exception $e) {
            Log::error("❌ Error en orquestador de sincronización Wispro: " . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("❌ Orquestador de sincronización falló: " . $exception->getMessage());
    }
}
