<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Plan;
use App\Models\Zone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelReader;
use Exception;


class ClientImportController extends Controller
{

    public function import(Request $request)
    {
        // Aumentar tiempo límite para archivos grandes
        set_time_limit(300); // 5 minutos

        $request->validate([
            'file' => ['required','file','mimes:xlsx,xls'],
        ]);

        $file = $request->file('file');

        try {
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

            if (empty($rows)) {
                return redirect()
                    ->route('import-clients.index')
                    ->with('error', 'El archivo no contiene datos válidos para importar.');
            }

            $contador = 0;
            $errores = [];

            // Iniciar transacción para que todo se haga o nada
            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                // Evita filas vacías o cortas
                if (!is_array($row) || count($row) < 16) {
                    continue;
                }

                $filaNumero = $index + 2; // +2 porque eliminamos encabezado y empezamos en 1

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

                // Validaciones básicas
                if (empty($nombre)) {
                    $errores[] = "Fila {$filaNumero}: El nombre es obligatorio";
                    continue;
                }

                if (empty($cedula)) {
                    $errores[] = "Fila {$filaNumero}: La cédula es obligatoria";
                    continue;
                }

                // Verificar si ya existe usuario con esta cédula o email
                $existingUser = User::where('id_number', $cedula)->first();
                if ($existingUser) {
                    $errores[] = "Fila {$filaNumero}: Ya existe un usuario con la cédula {$cedula}";
                    continue;
                }

                if (!empty($correo)) {
                    $existingEmail = User::where('email', $correo)->first();
                    if ($existingEmail) {
                        $errores[] = "Fila {$filaNumero}: Ya existe un usuario con el email {$correo}";
                        continue;
                    }
                }

                // Buscar plan por Mbps
                $plan = null;
                if (!empty($mbps)) {
                    $plan = Plan::where('mbps', $mbps)->first();
                    if (!$plan) {
                        $errores[] = "Fila {$filaNumero}: No se encontró un plan con {$mbps} Mbps";
                        continue;
                    }
                }

                // Buscar/crear zona
                $zone = null;
                if (!empty($zonaNombre)) {
                    $zone = Zone::firstOrCreate(['name' => $zonaNombre]);
                }

                $hashPassword = Hash::make('123456'); // Contraseña por defecto

                // Generar email si no existe
                $email = $correo ?: strtolower(str_replace(' ', '.', $nombre)) . '@example.com';

                User::create([
                    'name'           => $nombre,
                    'email'          => $email,
                    'phone'          => $celular,
                    'address'        => $direccion,
                    'id_number'      => $cedula,
                    'code'           => $abonado,
                    'plan_id'        => $plan?->id,
                    'zone_id'        => $zone?->id,
                    'status'         => $estatus,
                    'password'       => $hashPassword,
                    'role'           => 0,
                    'credit_balance' => 0,
                ]);

                $contador++;
            }

            // Si hay errores, cancelar la transacción
            if (!empty($errores)) {
                DB::rollBack();
                $mensajeError = "No se completó la importación. Se encontraron los siguientes errores:\n" . implode("\n", $errores);

                return redirect()
                    ->route('import-clients.index')
                    ->with('error', $mensajeError);
            }

            // Si todo está bien, confirmar la transacción
            DB::commit();

            return redirect()
                ->route('import-clients.index')
                ->with('success', "Importación completada exitosamente. Se crearon {$contador} clientes.");

        } catch (Exception $e) {
            // En caso de cualquier error, cancelar la transacción
            DB::rollBack();

            return redirect()
                ->route('import-clients.index')
                ->with('error', 'Error durante la importación: ' . $e->getMessage() . '. No se importó ningún dato.');
        }
    }
}
