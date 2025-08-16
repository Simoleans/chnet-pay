<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Plan;
use App\Models\Zone;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelReader;


class ClientImportController extends Controller
{

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required','file','mimes:xlsx,xls'],
        ]);

        $file = $request->file('file');

        // Detecta el tipo por extensión (ya validada en mimes)
        $ext  = strtolower($file->getClientOriginalExtension());
        $type = $ext === 'xlsx' ? ExcelReader::XLSX : ExcelReader::XLS;

        // Pasa el UploadedFile + tipo explícito (no uses getRealPath)
        $sheets = Excel::toArray([], $file, null, $type);
        $rows   = $sheets[0] ?? [];

        // Quitar encabezado con seguridad
        if (!empty($rows)) {
            array_shift($rows);
        }

        $contador = 0;

        foreach ($rows as $row) {
            // Evita filas vacías o cortas
            if (!is_array($row) || count($row) < 16) {
                continue;
            }

            $abonado    = trim((string)($row[2]  ?? ''));
            $cedula     = trim((string)($row[3]  ?? ''));
            $nombre     = trim((string)($row[4]  ?? ''));
            $direccion  = trim((string)($row[5]  ?? ''));
            $planNombre = trim((string)($row[6]  ?? ''));
            $mbps       = trim((string)($row[7]  ?? ''));
            $zonaNombre = trim((string)($row[13] ?? ''));
            $correo     = trim((string)($row[14] ?? ''));
            $celular    = trim((string)($row[15] ?? ''));
            $estatus    = 1;

            // Buscar plan por Mbps
            $plan = \App\Models\Plan::where('mbps', $mbps)->first();

            // Buscar/crear zona
            $zone = \App\Models\Zone::firstOrCreate(['name' => $zonaNombre]);

            \App\Models\User::create([
                'name'           => $nombre,
                'email'          => $correo ?: strtolower(str_replace(' ', '.', $nombre)) . '@example.com',
                'phone'          => $celular,
                'address'        => $direccion,
                'id_number'      => $cedula,
                'code'           => $abonado,
                'plan_id'        => $plan?->id,
                'zone_id'        => $zone?->id,
                'status'         => $estatus,
                'password'       => \Illuminate\Support\Facades\Hash::make('123456'),
                'role'           => 0,
                'credit_balance' => 0,
            ]);

            $contador++;
        }

        return redirect()
            ->route('import-clients.index')
            ->with('success', "Importación completada. Se crearon {$contador} clientes.");
    }
}
