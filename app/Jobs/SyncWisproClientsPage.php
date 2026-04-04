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

class SyncWisproClientsPage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $page;
    public int $perPage;

    public $tries   = 6;
    public $timeout = 900;

    /**
     * @param int $page     Número de página a sincronizar
     * @param int $perPage  Tamaño de página (debe coincidir con tu API)
     */
    public function __construct(int $page)
    {
        $this->page    = $page;
        $this->perPage = SyncWisproClients::PER_PAGE;
    }

    public function handle(WisproApiService $wisproApiService): void
    {
        $response = $wisproApiService->getClients($this->page, $this->perPage);

        if (!$response['success']) {
            // Lanzamos excepción para que Laravel reintente este job
            throw new \Exception(
                "Error al obtener clientes de Wispro (página {$this->page}): " .
                ($response['error'] ?? 'Error desconocido')
            );
        }

        $data    = $response['data'];
        $clients = $data['data'] ?? [];

        [$created, $updated, $skipped] = $this->processChunk($clients);

        Log::info("✅ Página {$this->page} completada - Creados: {$created}, Actualizados: {$updated}, Omitidos: {$skipped}");
    }

    /**
     * Procesa los clientes de ESTA página (mismo código que ya tienes).
     *
     * @return array [created, updated, skipped]
     */
    protected function processChunk(array $clients): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $now = now();

        foreach ($clients as $client) {
            try {

                $idNumber = $client['national_identification_number'] ?? null;
                $email    = $client['email'] ?? ($idNumber ? $idNumber . '@sincronizado.local' : null);
                $detail = $client['details'] ?? null;

                if (!$idNumber) {
                    $skipped++;
                    Log::warning("⚠️ Cliente omitido (sin cédula) - ID Wispro: " . ($client['id'] ?? 'N/A') . " | Nombre: " . ($client['name'] ?? 'Sin nombre') . " | Email: " . ($client['email'] ?? 'Sin email') . " | Página: {$this->page}");

                    continue;
                }

                $code = $client['custom_id'] ?? 'WIS-' . $client['national_identification_number'];

                DB::beginTransaction();

                // Primero busca por el identificador más confiable
                $existingUser = User::where('id_wispro', $client['id'])->first()
                ?? User::where('id_number', $detail.'-'. $idNumber)->first();

                if ($existingUser) {
                    // UPDATE: Actualizar todos los campos que pueden cambiar en Wispro
                    $existingUser->update([
                        'name'      => $client['name'] ?? 'Sin nombre',
                        'email'     => $email, // ✅ El email puede cambiar en Wispro
                        'phone'     => $client['phone_mobile'] ?? null,
                        'address'   => $client['address'] ?? null,
                        'zone'      => $client['zone_name'] ?? null,
                        'code'      => $code,
                        'id_wispro' => $client['id'], // Por si no lo tenía antes
                        'synced_at' => $now, // Marca última sincronización
                        'status'    => true, // Asegura que esté activo
                    ]);
                    $updated++;
                } else {
                    // CREATE: Nuevo usuario desde Wispro
                    User::create([
                        'name'      => $client['name'] ?? 'Sin nombre',
                        'email'     => $email,
                        'phone'     => $client['phone_mobile'] ?? null,
                        'address'   => $client['address'] ?? null,
                        'zone'      => $client['zone_name'] ?? null,
                        'code'      => $code,
                        'id_number' => $detail.'-'. $idNumber,
                        'id_wispro' => $client['id'],
                        'password'  => bcrypt($idNumber),
                        'synced_at' => $now, // Marca creación
                        'status'    => true,
                        'role'      => 0,
                    ]);
                    $created++;
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::warning("⚠️ Error procesando cliente {$client['name']} (página {$this->page}): {$e->getMessage()}");
            }
        }

        return [$created, $updated, $skipped];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("❌ Job de página {$this->page} falló: {$exception->getMessage()}");
    }
}
