<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Plan;
use App\Models\Zone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelReader;
use Exception;


class ClientImportController extends Controller
{

        public function import(Request $request)
    {
        // Aumentar tiempo límite para archivos grandes
        set_time_limit(300); // 5 minutos

        Log::info('=== INICIO DE IMPORTACIÓN DE CLIENTES ===');
        Log::info('Usuario que ejecuta: ' . (Auth::check() ? Auth::id() : 'No autenticado'));
        Log::info('Archivo original: ' . $request->file('file')?->getClientOriginalName());

        $request->validate([
            'file' => ['required','file','mimes:xlsx,xls'],
        ]);

        $file = $request->file('file');
        Log::info('Validación de archivo completada exitosamente');

        try {
            // Detecta el tipo por extensión (ya validada en mimes)
            $ext  = strtolower($file->getClientOriginalExtension());
            $type = $ext === 'xlsx' ? ExcelReader::XLSX : ExcelReader::XLS;
            Log::info("Tipo de archivo detectado: {$ext} -> {$type}");

            // Pasa el UploadedFile + tipo explícito (no uses getRealPath)
            $sheets = Excel::toArray([], $file, null, $type);
            $rows   = $sheets[0] ?? [];
            Log::info('Archivo Excel leído correctamente. Hojas encontradas: ' . count($sheets));
            Log::info('Filas totales en la primera hoja: ' . count($rows));

            // Quitar encabezado con seguridad
            if (!empty($rows)) {
                array_shift($rows);
                Log::info('Encabezado removido. Filas de datos: ' . count($rows));
            }

            if (empty($rows)) {
                Log::warning('El archivo no contiene datos válidos para importar');
                return redirect()
                    ->route('import-clients.index')
                    ->with('error', 'El archivo no contiene datos válidos para importar.');
            }

            $contador = 0;
            $errores = [];

            // Iniciar transacción para que todo se haga o nada
            Log::info('Iniciando transacción de base de datos');
            DB::beginTransaction();

            foreach ($rows as $index => $row) {
                // Evita filas vacías o cortas
                if (!is_array($row) || count($row) < 16) {
                    Log::debug("Fila {$index} omitida: muy corta o no es array. Columnas: " . count($row));
                    continue;
                }

                $filaNumero = $index + 2; // +2 porque eliminamos encabezado y empezamos en 1
                Log::debug("Procesando fila {$filaNumero}");

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

                Log::debug("Fila {$filaNumero} - Datos extraídos: nombre='{$nombre}', cedula='{$cedula}', email='{$correo}', mbps='{$mbps}'");

                // Validaciones básicas
                if (empty($nombre)) {
                    $error = "Fila {$filaNumero}: El nombre es obligatorio";
                    Log::warning($error);
                    $errores[] = $error;
                    continue;
                }

                if (empty($cedula)) {
                    $error = "Fila {$filaNumero}: La cédula es obligatoria";
                    Log::warning($error);
                    $errores[] = $error;
                    continue;
                }

                // Verificar si ya existe usuario con esta cédula o email
                $existingUser = User::where('id_number', $cedula)->first();
                if ($existingUser) {
                    $error = "Fila {$filaNumero}: Ya existe un usuario con la cédula {$cedula}";
                    Log::warning($error);
                    $errores[] = $error;
                    continue;
                }

                if (!empty($correo)) {
                    $existingEmail = User::where('email', $correo)->first();
                    if ($existingEmail) {
                        $error = "Fila {$filaNumero}: Ya existe un usuario con el email {$correo}";
                        Log::warning($error);
                        $errores[] = $error;
                        continue;
                    }
                }

                // Buscar plan por Mbps
                $plan = null;
                if (!empty($mbps)) {
                    Log::debug("Fila {$filaNumero} - Buscando plan con {$mbps} Mbps");
                    $plan = Plan::where('mbps', $mbps)->first();
                    if (!$plan) {
                        $error = "Fila {$filaNumero}: No se encontró un plan con {$mbps} Mbps";
                        Log::warning($error);
                        $errores[] = $error;
                        continue;
                    }
                    Log::debug("Fila {$filaNumero} - Plan encontrado: ID {$plan->id}");
                }

                // Buscar/crear zona
                $zone = null;
                if (!empty($zonaNombre)) {
                    Log::debug("Fila {$filaNumero} - Buscando/creando zona: {$zonaNombre}");
                    $zone = Zone::firstOrCreate(['name' => $zonaNombre]);
                    Log::debug("Fila {$filaNumero} - Zona: ID {$zone->id}");
                }

                $hashPassword = Hash::make('123456'); // Contraseña por defecto

                // Generar email si no existe
                $email = $correo ?: strtolower(str_replace(' ', '.', $nombre)) . '@example.com';
                Log::debug("Fila {$filaNumero} - Email final: {$email}");

                Log::info("Fila {$filaNumero} - Creando usuario: {$nombre} ({$cedula})");
                $newUser = User::create([
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

                Log::info("Fila {$filaNumero} - Usuario creado exitosamente con ID: {$newUser->id}");
                $contador++;
            }

            // Si hay errores, cancelar la transacción
            if (!empty($errores)) {
                Log::warning('Se encontraron errores durante la validación. Total de errores: ' . count($errores));
                Log::warning('Errores encontrados: ' . implode(' | ', $errores));
                DB::rollBack();
                Log::info('ROLLBACK ejecutado - No se guardó ningún dato');

                                $mensajeError = "No se completó la importación. Se encontraron los siguientes errores:\n" . implode("\n", $errores);

                Log::info('Preparando respuesta de ERROR');
                Log::info('Mensaje de error que se enviará: ' . $mensajeError);

                return redirect()
                    ->route('import-clients.index')
                    ->with('error', $mensajeError);
            }

            // Si todo está bien, confirmar la transacción
            Log::info('Todas las validaciones pasaron correctamente');
            Log::info("Procesados {$contador} usuarios exitosamente");
            DB::commit();
            Log::info('COMMIT ejecutado - Datos guardados en la base de datos');
            Log::info('=== FIN DE IMPORTACIÓN EXITOSA ===');

            $mensajeExito = "Importación completada exitosamente. Se crearon {$contador} clientes.";
            Log::info('Preparando respuesta de ÉXITO');
            Log::info('Mensaje de éxito que se enviará: ' . $mensajeExito);

            return redirect()
                ->route('import-clients.index')
                ->with('success', $mensajeExito);

        } catch (Exception $e) {
            // En caso de cualquier error, cancelar la transacción
            Log::error('EXCEPCIÓN CAPTURADA durante la importación');
            Log::error('Mensaje de error: ' . $e->getMessage());
            Log::error('Archivo: ' . $e->getFile() . ' - Línea: ' . $e->getLine());
            Log::error('Stack trace: ' . $e->getTraceAsString());

                        DB::rollBack();
            Log::info('ROLLBACK ejecutado por excepción - No se guardó ningún dato');
            Log::info('=== FIN DE IMPORTACIÓN CON ERROR ===');

            $mensajeException = 'Error durante la importación: ' . $e->getMessage() . '. No se importó ningún dato.';
            Log::info('Preparando respuesta de EXCEPCIÓN');
            Log::info('Mensaje de excepción que se enviará: ' . $mensajeException);

            return redirect()
                ->route('import-clients.index')
                ->with('error', $mensajeException);
        }
    }
}
