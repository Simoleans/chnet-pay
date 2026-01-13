<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Zone;
use App\Models\Plan;
use App\Services\WisproApiService;
use App\Jobs\SyncWisproClients;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    protected $wisproApiService;

    public function __construct(WisproApiService $wisproApiService)
    {
        $this->wisproApiService = $wisproApiService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Clientes locales (role = 0) - Excluir al usuario actual
        $queryClients = User::active()->where('id','!=',Auth::user()->id)->where('role', 0);

        // Filtro de búsqueda local (solo para tab de Sistema)
        $localSearch = $request->get('local_search', '');
        if (!empty($localSearch)) {
            $queryClients->where('code', 'like', '%' . $localSearch . '%')
                  ->orWhere('id_number', 'like', '%' . $localSearch . '%')
                  ->orWhere('name', 'like', '%' . $localSearch . '%')
                  ->orWhere('email', 'like', '%' . $localSearch . '%');
        }

        $users = $queryClients->orderBy('id','desc')->paginate(10)->appends($request->query());

        // Administradores (role = 1) - Excluir al usuario actual
        $queryAdmins = User::active()->where('id','!=',Auth::user()->id)->where('role', 1);

        // Filtro de búsqueda de administradores
        $adminSearch = $request->get('admin_search', '');
        if (!empty($adminSearch)) {
            $queryAdmins->where(function ($q) use ($adminSearch) {
                $q->where('name', 'like', '%' . $adminSearch . '%')
                  ->orWhere('id_number', 'like', '%' . $adminSearch . '%')
                  ->orWhere('email', 'like', '%' . $adminSearch . '%');
            });
        }

        $adminPage = $request->get('admin_page', 1);
        $admins = $queryAdmins->orderBy('id','desc')->paginate(10, ['*'], 'admin_page', $adminPage)->appends($request->query());

        // Clientes de Wispro
        $wisproClients = [];
        $wisproPage = $request->get('wispro_page', 1);
        $wisproPerPage = $request->get('wispro_per_page', 20);
        $wisproSearch = $request->get('wispro_search', '');

        // Preparar filtros para Wispro
        $wisproFilters = [];
        if (!empty($wisproSearch)) {
            $wisproFilters['custom_id_eq'] = $wisproSearch;
        }

        // Obtener clientes de Wispro con o sin filtros
        $wisproResponse = $this->wisproApiService->getClientsWithFilters($wisproFilters, $wisproPage, $wisproPerPage);

        if ($wisproResponse['success']) {
            $wisproClients = $wisproResponse['data'];
        } else {
            Log::warning('Error al obtener clientes de Wispro API: ' . ($wisproResponse['error'] ?? 'Error desconocido'));
        }

        return Inertia::render('User/Index', [
            'data' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
            'admins' => $admins->items(),
            'paginationAdmins' => [
                'current_page' => $admins->currentPage(),
                'last_page' => $admins->lastPage(),
                'per_page' => $admins->perPage(),
                'total' => $admins->total(),
                'from' => $admins->firstItem(),
                'to' => $admins->lastItem(),
            ],
            'filters' => [
                'local_search' => $localSearch,
                'wispro_search' => $wisproSearch,
                'admin_search' => $adminSearch,
            ],
            'wispro_clients' => $wisproClients,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'zone' => 'nullable|string',
            'code' => 'nullable|string|max:50|unique:users',
            'nationality' => 'required|string|in:V,E',
            'id_number' => 'required|string|max:20',
            'password' => 'required|string|min:8',
            'status' => 'nullable|boolean',
            'role' => 'nullable|integer|in:0,1',
        ]);

        // Concatenar nacionalidad con número de cédula
        $fullIdNumber = $request->nationality . '-' . $request->id_number;

        // Validar que la cédula completa sea única
        $existingUser = User::where('id_number', $fullIdNumber)->first();
        if ($existingUser) {
            return back()->withErrors(['id_number' => 'Esta cédula ya está registrada.']);
        }

        // Generar código automático para trabajadores (role = 1) si no se proporciona
        $code = $request->code;
        if (empty($code) && ($request->role === 1 || $request->role === '1')) {
            // Generar código automático: ADMIN-{timestamp}
            $code = 'ADMIN-' . time();
        }

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'zone' => $request->zone,
            'code' => $code,
            'id_number' => $fullIdNumber,
            'password' => bcrypt($request->password),
            'status' => $request->status ?? true,
            'role' => $request->role ?? 1,
        ]);

        return redirect()->route('users.index')->with('success', 'Trabajador creado exitosamente.');
    }

    /**
     * Display the specified resource (Cliente Local)
     * Muestra toda la información del cliente + pagos
     */
    public function show(string $id)
    {
        $user = User::with(['payments', 'invoices'])->findOrFail($id);

        // Obtener pagos del usuario
        $payments = $user->payments()
            ->orderBy('payment_date', 'desc')
            ->get()
            ->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'reference' => $payment->reference,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date ? $payment->payment_date->format('d/m/Y') : null,
                    'bank' => $payment->bank,
                    'phone' => $payment->phone,
                    'verify_payments' => $payment->verify_payments,
                ];
            });

        // Obtener facturas del usuario
        $invoices = $user->invoices()
            ->orderBy('period', 'desc')
            ->get()
            ->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'code' => $invoice->code,
                    'period' => $invoice->period ? $invoice->period->format('Y-m') : null,
                    'amount_due' => $invoice->amount_due,
                    'amount_paid' => $invoice->amount_paid,
                    'status' => $invoice->status,
                ];
            });

        // Obtener contrato y plan si el usuario tiene id_wispro
        $contract = null;
        $plan = null;

        if ($user->id_wispro) {
            $contractsResponse = $this->wisproApiService->getClientContracts($user->id_wispro);

            if ($contractsResponse['success'] && !empty($contractsResponse['data']['data'])) {
                $contractData = $contractsResponse['data']['data'][0]; // Obtener el primer contrato

                // Obtener información del plan
                if (!empty($contractData['plan_id'])) {
                    $planResponse = $this->wisproApiService->getPlanById($contractData['plan_id']);

                    if ($planResponse['success']) {
                        $planData = $planResponse['data']['data'] ?? $planResponse['data'];
                        $plan = [
                            'name' => $planData['name'] ?? 'N/A',
                            'price' => $planData['price'] ?? 0,
                        ];
                    }
                }

                $contract = [
                    'start_date' => $contractData['start_date'] ?? null,
                    'latitude' => $contractData['latitude'] ?? null,
                    'longitude' => $contractData['longitude'] ?? null,
                    'ip' => $contractData['ip'] ?? null,
                    'state' => $contractData['state'] ?? null,
                ];
            }
        }

        // Obtener facturas de Wispro si el usuario tiene código
        $wisproInvoices = $this->getWisproInvoices($user->code);

        return Inertia::render('User/Show', [
            'user' => $user,
            'payments' => $payments,
            'invoices' => $invoices,
            'contract' => $contract,
            'plan' => $plan,
            'isWispro' => false,
            'existsInLocal' => true,
            'wisproInvoices' => $wisproInvoices,
        ]);
    }

    /**
     * Mostrar cliente de Wispro
     * Verifica si existe en BD local y muestra botón correspondiente
     */
    public function showWispro(string $wisproId)
    {
        try {
            // Obtener datos del cliente desde Wispro API
            $wisproResponse = $this->wisproApiService->getClient($wisproId);

            if (!$wisproResponse['success']) {
                return redirect()->route('users.index')
                    ->with('error', 'No se pudo obtener la información del cliente de Wispro');
            }

            $wisproClient = $wisproResponse['data']['data'] ?? $wisproResponse['data'];

            // Verificar si existe en BD local
            $existsInLocal = User::existsInLocal($wisproId);
            $localUser = null;
            $payments = [];
            $invoices = [];

            if ($existsInLocal) {
                $localUser = User::findByWisproId($wisproId);

                // Obtener pagos del usuario local
                $payments = $localUser->payments()
                    ->orderBy('payment_date', 'desc')
                    ->get()
                    ->map(function ($payment) {
                        return [
                            'id' => $payment->id,
                            'reference' => $payment->reference,
                            'amount' => $payment->amount,
                            'payment_date' => $payment->payment_date ? $payment->payment_date->format('d/m/Y') : null,
                            'bank' => $payment->bank,
                            'phone' => $payment->phone,
                            'verify_payments' => $payment->verify_payments,
                        ];
                    });

                // Obtener facturas del usuario local
                $invoices = $localUser->invoices()
                    ->orderBy('period', 'desc')
                    ->get()
                    ->map(function ($invoice) {
                        return [
                            'id' => $invoice->id,
                            'code' => $invoice->code,
                            'period' => $invoice->period ? $invoice->period->format('Y-m') : null,
                            'amount_due' => $invoice->amount_due,
                            'amount_paid' => $invoice->amount_paid,
                            'status' => $invoice->status,
                        ];
                    });
            }

            // Obtener contrato y plan del cliente de Wispro
            $contract = null;
            $plan = null;

            $contractsResponse = $this->wisproApiService->getClientContracts($wisproId);

            if ($contractsResponse['success'] && !empty($contractsResponse['data']['data'])) {
                $contractData = $contractsResponse['data']['data'][0]; // Obtener el primer contrato

                // Obtener información del plan
                if (!empty($contractData['plan_id'])) {
                    $planResponse = $this->wisproApiService->getPlanById($contractData['plan_id']);

                    if ($planResponse['success']) {
                        $planData = $planResponse['data']['data'] ?? $planResponse['data'];
                        $plan = [
                            'name' => $planData['name'] ?? 'N/A',
                            'price' => $planData['price'] ?? 0,
                        ];
                    }
                }

                $contract = [
                    'start_date' => $contractData['start_date'] ?? null,
                    'latitude' => $contractData['latitude'] ?? null,
                    'longitude' => $contractData['longitude'] ?? null,
                    'ip' => $contractData['ip'] ?? null,
                    'state' => $contractData['state'] ?? null,
                ];
            }

            // Obtener facturas de Wispro por custom_id
            $wisproInvoices = $this->getWisproInvoices($wisproClient['custom_id'] ?? null);

            return Inertia::render('User/Show', [
                'user' => $wisproClient,
                'localUser' => $localUser,
                'payments' => $payments,
                'invoices' => $invoices,
                'contract' => $contract,
                'plan' => $plan,
                'isWispro' => true,
                'existsInLocal' => $existsInLocal,
                'wisproInvoices' => $wisproInvoices,
            ]);

        } catch (\Exception $e) {
            Log::error('Error mostrando cliente Wispro: ' . $e->getMessage());
            return redirect()->route('users.index')
                ->with('error', 'Error al obtener la información del cliente');
        }
    }

    /**
     * Sincronizar un solo cliente de Wispro a BD local
     */
    public function syncSingleWisproClient(string $wisproId)
    {
        try {
            // Obtener datos del cliente desde Wispro API
            $wisproResponse = $this->wisproApiService->getClient($wisproId);

            if (!$wisproResponse['success']) {
                return back()->with('error', 'No se pudo obtener la información del cliente de Wispro');
            }

            $client = $wisproResponse['data']['data'] ?? $wisproResponse['data'];

            // Verificar si ya existe
            if (User::existsInLocal($wisproId)) {
                return back()->with('error', 'Este cliente ya existe en la base de datos local');
            }

            // Crear usuario local con datos de Wispro
            User::create([
                'name' => $client['name'],
                'email' => $client['email'],
                'phone' => $client['phone_mobile'] ?? null,
                'address' => $client['address'] ?? $client['street'] ?? null,
                'zone' => $client['zone_name'] ?? null,
                'code' => $client['custom_id'] ?? 'WISPRO-' . $client['public_id'],
                'id_number' => $client['national_identification_number'] ?? 'V-00000000',
                'id_wispro' => $client['id'],
                'password' => bcrypt($client['national_identification_number']),
                'status' => true,
                'role' => 0,
            ]);

            return back()->with('success', 'Cliente sincronizado exitosamente en la base de datos local');

        } catch (\Exception $e) {
            Log::error('Error sincronizando cliente Wispro: ' . $e->getMessage());
            return back()->with('error', 'Error al sincronizar el cliente');
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'zone' => 'nullable|string',
            'code' => 'required|string|max:50|unique:users,code,' . $id,
            'nationality' => 'required|string|in:V,E',
            'id_number' => 'required|string|max:20',
            'password' => 'nullable|string|min:8',
            'status' => 'required|boolean',
            'role' => 'required|integer|in:0,1',
        ]);

        // Concatenar nacionalidad con número de cédula
        $fullIdNumber = $request->nationality . '-' . $request->id_number;

        // Validar que la cédula completa sea única (excluyendo el usuario actual)
        $existingUser = User::where('id_number', $fullIdNumber)->where('id', '!=', $id)->first();
        if ($existingUser) {
            return back()->withErrors(['id_number' => 'Esta cédula ya está registrada.']);
        }

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'zone' => $request->zone,
            'code' => $request->code,
            'id_number' => $fullIdNumber,
            'status' => $request->status,
            'role' => $request->role,
        ];

        // Solo actualizar la contraseña si se proporciona
        if ($request->filled('password')) {
            $updateData['password'] = bcrypt($request->password);
        }

        $user->update($updateData);

        return redirect()->route('users.index')->with('success', 'Usuario actualizado exitosamente.');
    }

    /**
     * Actualizar cliente (puede ser local o de Wispro)
     * Si tiene id_wispro, actualiza en Wispro API y en local
     * Si no tiene id_wispro, solo actualiza en local
     */
    public function updateClient(Request $request, string $id)
    {
        try {
            $user = User::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255',
                'phone' => 'nullable|string|max:20',
                'address' => 'required|string|max:500',
                'password' => 'nullable|string|min:8', // Solo para clientes locales
            ]);

            // Preparar datos para actualizar localmente
            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
            ];

            // Solo actualizar password si el usuario NO tiene id_wispro (cliente local)
            if (!$user->id_wispro && $request->filled('password')) {
                $updateData['password'] = bcrypt($request->password);
            }

            // Si el usuario tiene id_wispro, actualizar en Wispro API
            $updatedInWispro = false;
            if ($user->id_wispro) {
                $wisproData = [
                    'name' => $request->name,
                    'email' => $request->email,
                    'phone_mobile' => $request->phone, // Teléfono en Wispro
                    'street' => $request->address, // En Wispro se llama 'street'
                ];

                $wisproResponse = $this->wisproApiService->updateClient($user->id_wispro, $wisproData);

                if (!$wisproResponse['success']) {
                    return back()->withErrors([
                        'wispro' => 'Error al actualizar en Wispro: ' . ($wisproResponse['message'] ?? 'Error desconocido')
                    ]);
                }

                $updatedInWispro = true;
            }

            // Actualizar en la base de datos local
            $user->update($updateData);

            // Mensaje según si se actualizó en Wispro o no
            $successMessage = $updatedInWispro
                ? 'Cliente actualizado exitosamente en el sistema y sincronizado con Wispro.'
                : 'Cliente actualizado exitosamente en el sistema.';

            return back()->with('success', $successMessage);

        } catch (\Exception $e) {
            Log::error('Error al actualizar cliente: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Error al actualizar el cliente.']);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }

    /**
     * Busca un usuario por código y retorna información de deuda
     */
    public function searchByCode(string $code)
    {
        try {
            $user = User::where('code', $code)
                ->where('status', true)
                ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró ningún usuario con ese código'
                ]);
            }

            // Obtener contrato y plan si el usuario tiene id_wispro
            $plan = null;
            if ($user->id_wispro) {
                $contractsResponse = $this->wisproApiService->getClientContracts($user->id_wispro);

                if ($contractsResponse['success'] && !empty($contractsResponse['data']['data'])) {
                    $contractData = $contractsResponse['data']['data'][0]; // Obtener el primer contrato

                    // Obtener información del plan
                    if (!empty($contractData['plan_id'])) {
                        $planResponse = $this->wisproApiService->getPlanById($contractData['plan_id']);

                        if ($planResponse['success']) {
                            $planData = $planResponse['data']['data'] ?? $planResponse['data'];
                            $plan = [
                                'name' => $planData['name'] ?? 'N/A',
                                'price' => $planData['price'] ?? 0,
                            ];
                        }
                    }
                }
            }

            // Obtener facturas de Wispro pendientes
            $wisproInvoices = $this->getWisproInvoices($user->code);

            // Filtrar solo las facturas pendientes
            $pendingInvoices = collect($wisproInvoices)->filter(function ($invoice) {
                return $invoice['state'] === 'pending';
            })->values()->toArray();

            $userData = [
                'id' => $user->id,
                'name' => $user->name,
                'code' => $user->code,
                'zone' => $user->zone,
                'plan' => $plan,
                'credit_balance' => $user->credit_balance ?? 0,
                'total_debt' => $user->due,
                'pending_invoices' => $pendingInvoices,
                'pending_invoices_count' => count($pendingInvoices),
            ];

            return response()->json([
                'success' => true,
                'data' => $userData
            ]);
        } catch (\Exception $e) {
            Log::error('Error buscando usuario por código: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Sincronizar todos los clientes de Wispro con la base de datos local
     * Despacha un Job en background para evitar timeouts
     */
    public function syncWisproClients(Request $request)
    {
        try {
            // Verificar que el usuario sea admin
            if (Auth::user()->role !== 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para realizar esta acción'
                ], 403);
            }

            // Verificar si ya hay un job de sincronización en la cola o ejecutándose
            $pendingJobs = DB::table('jobs')
                ->where('queue', 'default')
                ->where('payload', 'like', '%SyncWisproClients%')
                ->count();


            if ($pendingJobs > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya hay una sincronización en progreso. Por favor espera a que termine.'
                ], 409);
            }

            // Despachar el job
            SyncWisproClients::dispatch();

            //Log::info("🚀 Job de sincronización despachado por usuario: " . Auth::user()->name);

            return response()->json([
                'success' => true,
                'message' => 'Sincronización iniciada en segundo plano. Revisa los logs para ver el progreso.'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al iniciar sincronización de Wispro: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al iniciar sincronización: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener las facturas de Wispro por código del cliente (custom_id o code)
     *
     * @param string|null $customId Código del cliente
     * @return array Array de facturas (puede estar vacío)
     */
    private function getWisproInvoices(?string $customId): array
    {
        if (!$customId) {
            return [];
        }

        $invoicesResponse = $this->wisproApiService->getInvoicesByCustomId($customId);

        if (!$invoicesResponse['success'] || empty($invoicesResponse['data']['data'])) {
            return [];
        }

        // Mapear todas las facturas
        return collect($invoicesResponse['data']['data'])->map(function ($invoice) {
            return [
                'id' => $invoice['id'],
                'invoice_number' => $invoice['invoice_number'] ?? 'N/A',
                'client_name' => $invoice['client_name'] ?? 'N/A',
                'client_address' => $invoice['client_address'] ?? 'N/A',
                'first_due_date' => $invoice['first_due_date'] ?? null,
                'second_due_date' => $invoice['second_due_date'] ?? null,
                'state' => $invoice['state'] ?? 'pending',
                'amount' => $invoice['amount'] ?? 0,
                'from' => $invoice['from'] ?? null,
                'to' => $invoice['to'] ?? null,
                'issued_at' => $invoice['issued_at'] ?? null,
            ];
        })->toArray();
    }

}
