<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\WisproApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Job para sincronizar clientes de Wispro a la base de datos local
 *
 * Características:
 * - Procesa por chunks (páginas de la API)
 * - Usa transacciones para integridad de datos
 * - Maneja errores y reintentos
 */
class SyncWisproClients implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de reintentos permitidos
     */
    public $tries = 3;

    /**
     * Tiempo máximo de ejecución en segundos (15 minutos)
     */
    public $timeout = 900;

    /**
     * Registros por página a solicitar de Wispro
     */
    protected $perPage = 100;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(WisproApiService $wisproApiService): void
    {
        Log::info("🚀 Iniciando sincronización de Wispro");

        try {
            // Obtener primera página para conocer el total
            $response = $wisproApiService->getClients(1, $this->perPage);

            if (!$response['success']) {
                throw new \Exception('Error al obtener clientes de Wispro: ' . ($response['error'] ?? 'Error desconocido'));
            }

            $responseData = $response['data'];
            $totalPages = $responseData['meta']['pagination']['total_pages'] ?? 1;
            $totalRecords = $responseData['meta']['pagination']['total_records'] ?? 0;

            Log::info("📊 Total a procesar: {$totalPages} páginas ({$totalRecords} registros)");

            // Estadísticas globales
            $stats = [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => []
            ];

            // Procesar cada página (chunk)
            for ($page = 1; $page <= $totalPages; $page++) {
                // Si no es la primera página, obtener los datos
                if ($page > 1) {
                    $response = $wisproApiService->getClients($page, $this->perPage);

                    if (!$response['success']) {
                        Log::warning("⚠️ Error en página {$page}: " . ($response['error'] ?? 'Error desconocido'));
                        $stats['errors'][] = [
                            'page' => $page,
                            'error' => $response['error'] ?? 'Error desconocido'
                        ];
                        continue;
                    }

                    $responseData = $response['data'];
                }

                $clients = $responseData['data'] ?? [];

                // Procesar chunk en transacción
                $chunkResult = $this->processChunk($clients);

                // Acumular estadísticas
                $stats['created'] += $chunkResult['created'];
                $stats['updated'] += $chunkResult['updated'];
                $stats['skipped'] += $chunkResult['skipped'];
                $stats['errors'] = array_merge($stats['errors'], $chunkResult['errors']);

                // Log cada 10 páginas
                if ($page % 10 === 0) {
                    Log::info("📄 Progreso: {$page}/{$totalPages} páginas - Creados: {$stats['created']}, Actualizados: {$stats['updated']}, Omitidos: {$stats['skipped']}");
                }
            }

            Log::info("✅ Sincronización completada - Creados: {$stats['created']}, Actualizados: {$stats['updated']}, Omitidos: {$stats['skipped']}, Errores: " . count($stats['errors']));

        } catch (\Exception $e) {
            Log::error("❌ Error en sincronización de Wispro: " . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Procesar un chunk (página) de clientes en una transacción
     */
    protected function processChunk(array $clients): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($clients as $client) {
            try {
                // Usar transacción para cada cliente
                DB::beginTransaction();

                $idNumber = $client['national_identification_number'] ?? null;

                // Saltar si no tiene número de identificación
                if (!$idNumber) {
                    $skipped++;
                    DB::rollBack();
                    continue;
                }

                // Buscar usuario existente por id_wispro o por id_number
                $existingUser = User::where('id_wispro', $client['id'])
                    ->orWhere(function($query) use ($idNumber) {
                        $query->where('id_number', 'like', '%' . $idNumber);
                              //->orWhereLike('id_number', '%' . $idNumber . '%');
                              //->orWhere('id_number', 'E-' . $idNumber);
                    })
                    ->first();

                if ($existingUser) {
                    // Actualizar solo: zona, dirección, email y nombre
                    // NO actualiza: code, id_number, status, role, password
                    $existingUser->update([
                        'name' => $client['name'] ?? 'Sin nombre',
                        'email' => $client['email'] ?? $idNumber . '@sincronizado.local',
                        'address' => $client['address'] ?? null,
                        'zone' => $client['zone_name'] ?? null,
                    ]);
                    $updated++;
                } else {
                    // Crear nuevo usuario con todos los datos
                    User::create([
                        'name' => $client['name'] ?? 'Sin nombre',
                        'email' => $client['email'] ?? $idNumber . '@sincronizado.local',
                        'phone' => $client['phone_mobile'] ?? null,
                        'address' => $client['address'] ?? null,
                        'zone' => $client['zone_name'] ?? null,
                        'code' => $client['custom_id'] ?? 'WIS-' . $client['id'],
                        'id_number' => 'V-' . $idNumber,
                        'id_wispro' => $client['id'],
                        'password' => bcrypt($idNumber),
                        'status' => true,
                        'role' => 0,
                    ]);
                    $created++;
                }

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                $errors[] = [
                    'client' => $client['name'] ?? 'Desconocido',
                    'id_wispro' => $client['id'] ?? null,
                    'error' => $e->getMessage()
                ];
                Log::warning("⚠️ Error procesando cliente {$client['name']}: {$e->getMessage()}");
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }

    /**
     * Callback cuando el job falla
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("❌ Job de sincronización falló completamente: " . $exception->getMessage());
    }
}

