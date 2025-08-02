<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Plan;
use App\Models\Zone;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Hash;

class ImportClients extends Command
{
    protected $signature = 'import:clients {file}';
    protected $description = 'Importar clientes desde un archivo Excel';

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error("El archivo no existe: {$filePath}");
            return Command::FAILURE;
        }

        $rows = Excel::toArray([], $filePath)[0];

        // Saltar encabezado (primera fila)
        unset($rows[0]);

        $contador = 0;

        foreach ($rows as $row) {
            $abonado = trim($row[2]); // Columna Abonado
            $cedula = trim($row[3]);
            $nombre = trim($row[4]);
            $direccion = trim($row[5]);
            $planNombre = trim($row[6]);
            $mbps = trim($row[7]);
            $zonaNombre = trim($row[13]);
            $correo = trim($row[14]);
            $celular = trim($row[15]);
            $estatus = strtolower(trim($row[10])) === 'activo' ? 1 : 0;

            // Buscar plan por Mbps
            $plan = Plan::where('mbps', $mbps)->first();
            if (!$plan) {
                $this->warn("No se encontró plan para {$mbps} Mbps, cliente: {$nombre}");
                continue;
            }

            // Buscar zona por nombre
            $zone = Zone::where('name', $zonaNombre)->first();
            if (!$zone) {
                $this->warn("No se encontró zona: {$zonaNombre}, cliente: {$nombre}");
                continue;
            }

            // Crear usuario
            User::create([
                'name' => $nombre,
                'email' => $correo ?: strtolower(str_replace(' ', '.', $nombre)) . '@example.com',
                'phone' => $celular,
                'address' => $direccion,
                'id_number' => $cedula,
                'code' => $abonado,
                'plan_id' => $plan->id,
                'zone_id' => $zone->id,
                'status' => $estatus,
                'password' => Hash::make('123456'), // Contraseña por defecto
                'role' => 'cliente',
                'credit_balance' => 0
            ]);

            $contador++;
        }

        $this->info("Importación completada. Se crearon {$contador} clientes.");
        return Command::SUCCESS;
    }
}
