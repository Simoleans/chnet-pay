<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Plan;
use App\Models\Zone;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;

class ClientImportController extends Controller
{

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);

        $path = $request->file('file')->getRealPath();
        $rows = Excel::toArray([], $path)[0];

        // Quitar encabezado
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
            $estatus = 1;

            // Buscar plan por Mbps
            $plan = Plan::where('mbps', $mbps)->first();
            /* if (!$plan) {
                continue; // Si no existe el plan, saltar
            } */

            // Buscar zona por nombre o crearla si no existe
            $zone = Zone::firstOrCreate(['name' => $zonaNombre]);

            // Crear usuario
            User::create([
                'name' => $nombre,
                'email' => $correo ?: strtolower(str_replace(' ', '.', $nombre)) . '@example.com',
                'phone' => $celular,
                'address' => $direccion,
                'id_number' => $cedula,
                'code' => $abonado,
                'plan_id' => $plan?->id,
                'zone_id' => $zone?->id,
                'status' => $estatus,
                'password' => Hash::make('123456'),
                'role' => 0,
                'credit_balance' => 0
            ]);

            $contador++;
        }

        return redirect()->route('import-clients.index')->with('success', "Importación completada. Se crearon {$contador} clientes.");
    }
}
