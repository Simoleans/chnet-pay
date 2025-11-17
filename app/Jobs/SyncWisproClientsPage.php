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
    public function __construct(int $page, int $perPage = 100)
    {
        $this->page    = $page;
        $this->perPage = $perPage;
    }

    public function handle(WisproApiService $wisproApiService): void
    {
        Log::info("📄 Sincronizando página {$this->page} de Wispro (perPage={$this->perPage})");

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

        foreach ($clients as $client) {
            try {
                DB::beginTransaction();

                $idNumber = $client['national_identification_number'] ?? null;
                $email    = $client['email'] ?? ($idNumber ? $idNumber . '@sincronizado.local' : null);

                if (!$idNumber) {
                    $skipped++;
                    DB::rollBack();
                    continue;
                }

                $existingUser = User::where('id_wispro', $client['id'])
                    ->orWhere(function ($query) use ($idNumber) {
                        $query->where('id_number', 'like', '%' . $idNumber);
                    })
                    ->orWhere('email', $email)
                    ->first();

                if ($existingUser) {
                    $updateData = [
                        'name'    => $client['name'] ?? 'Sin nombre',
                        'email'   => $email,
                        'address' => $client['address'] ?? null,
                        'zone'    => $client['zone_name'] ?? null,
                    ];

                    /* if (!$existingUser->id_wispro) {
                        $updateData['id_wispro'] = $client['id'];
                    } */

                    $existingUser->update($updateData);
                    $updated++;
                } else {
                    User::create([
                        'name'      => $client['name'] ?? 'Sin nombre',
                        'email'     => $email,
                        'phone'     => $client['phone_mobile'] ?? null,
                        'address'   => $client['address'] ?? null,
                        'zone'      => $client['zone_name'] ?? null,
                        'code'      => $client['custom_id'] ?? 'WIS-' . $client['id'],
                        'id_number' => 'V-' . $idNumber,
                        'id_wispro' => $client['id'],
                        'password'  => bcrypt($idNumber),
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
