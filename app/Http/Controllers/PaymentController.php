<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Helpers\BncHelper;
use App\Helpers\BncLogger;
use App\Exports\PaymentsExport;
use App\Services\WisproApiService;
use App\Http\Requests\SendC2PRequest;
use App\Http\Requests\ValidatePaymentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\User;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Payment::with(['user', 'invoice'])->orderBy('id', 'desc');

        // Filtro de búsqueda por referencia o código de abonado
        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('reference', 'like', '%' . $searchTerm . '%')
                  ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                      $userQuery->where('code', 'like', '%' . $searchTerm . '%');
                  });
            });
        }

        // Filtro por rango de fechas
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('payment_date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('payment_date', '<=', $request->date_to);
        }

        $payments = $query->paginate(15)->appends($request->query());

        // Formatear los datos para la vista
        $formattedPayments = $payments->getCollection()->map(function ($payment) {
            return [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'amount' => $payment->amount,
                'amount_bs' => $payment->amount * (\App\Helpers\BncHelper::getBcvRatesCached()['Rate'] ?? 1),
                'payment_date' => $payment->payment_date ? $payment->payment_date->format('d/m/Y') : null,
                'bank' => $payment->bank,
                'phone' => $payment->phone,
                'id_number' => $payment->id_number,
                'user_name' => $payment->user ? $payment->user->name : 'N/A',
                'user_code' => $payment->user ? $payment->user->code : 'N/A',
                'invoice_period' => $payment->invoice && $payment->invoice->period ?
                    $payment->invoice->period->format('Y-m') : 'Sin factura',
                'created_at' => $payment->created_at ? $payment->created_at->format('d/m/Y H:i') : null,
                'image_path' => $payment->image_path,
                'verify_payments' => $payment->verify_payments,
            ];
        });

        return Inertia::render('Payments/Index', [
            'data' => $formattedPayments,
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ],
            'filters' => [
                'search' => $request->search ?? '',
                'date_from' => $request->date_from ?? '',
                'date_to' => $request->date_to ?? '',
            ],
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
        try {
            // Si viene user_id, usar ese usuario; si no, usar el autenticado (si existe)
            if ($request->has('user_id')) {
                $user = User::findOrFail($request->user_id);
            } elseif (Auth::check()) {
                $user = Auth::user();
            } else {
                Log::error('PAYMENT STORE: No hay usuario especificado ni autenticado');

                if ($request->is('quick-payment')) {
                    return back()->with('error', 'Debe especificar un usuario para el pago');
                }

                return redirect()->back()->with('error', 'Debe especificar un usuario para el pago');
            }

            $validated = $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'reference' => 'nullable|string|max:255',
                'amount' => 'required|numeric|min:0.01', // Este viene en bolívares
                'nationality' => 'required|string|in:V,E,J',
                'id_number' => 'required|string|max:20',
                'bank' => 'required|string|max:100',
                'phone' => 'required|string|max:20',
                'payment_date' => 'required|date',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:4048', // 4MB max
                'invoice_wispro' => 'nullable|string|max:255', // ID de la factura de Wispro
            ]);

        // Validar que la referencia no exista previamente
        if (!empty($validated['reference']) && Payment::where('reference', $validated['reference'])->exists()) {
            if ($request->is('quick-payment')) {
                return back()->with('error', 'Esta referencia de pago ya fue registrada anteriormente.');
            }
            return redirect()->back()->with('error', 'Esta referencia de pago ya fue registrada anteriormente.');
        }

        // Manejar la subida de imagen
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('payment-receipts', 'public');
        }

        // Concatenar nacionalidad con número de cédula
        $fullIdNumber = $validated['nationality'] . '-' . $validated['id_number'];

        // Obtener la tasa BCV actual
        $bcvData = BncHelper::getBcvRatesCached();
        $bcvRate = $bcvData['Rate'] ?? null;

        if (!$bcvRate) {
            if ($request->is('quick-payment')) {
                return back()->with('error', 'No se pudo obtener la tasa BCV. Intente nuevamente.');
            }
            return redirect()->back()->with('error', 'No se pudo obtener la tasa BCV. Intente nuevamente.');
        }

        // Convertir el monto de bolívares a dólares
        $amountInUSD = $validated['amount'] / $bcvRate;

        // PASO 1: Registrar SOLO el pago (sin aplicar a facturas hasta verificación)
        $originalPayment = Payment::create([
            'reference' => $validated['reference'],
            'user_id' => $user->id,
            'invoice_id' => null, // Sin factura asociada hasta verificación
            'amount' => $amountInUSD, // En USD
            'id_number' => $fullIdNumber,
            'bank' => $validated['bank'],
            'phone' => $validated['phone'],
            'payment_date' => $validated['payment_date'],
            'image_path' => $imagePath, // Guardar la ruta de la imagen
            'verify_payments' => false, // Sin verificar por defecto
            'invoice_wispro' => $validated['invoice_wispro'] ?? null, // ID de factura de Wispro
        ]);

        // Preparar mensaje informativo
        $message = 'Pago registrado exitosamente. Pendiente de verificación por el operador.';

        // Determinar la redirección apropiada
        if ($request->is('quick-payment')) {
            // Para pago rápido desde login, usar respuesta JSON compatible con Inertia
            return back()->with('success', $message);
        }

        return redirect()->route('dashboard')->with('success', $message);

        } catch (\Exception $e) {
            if ($request->is('quick-payment')) {
                return back()->with('error', 'Error al procesar el pago: ' . $e->getMessage());
            }

            return redirect()->back()->with('error', 'Error al procesar el pago: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Payment $payment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Payment $payment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Payment $payment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payment)
    {
        //
    }

        /**
     * Toggle payment verification status
     */
    public function toggleVerification(Request $request, Payment $payment)
    {
        try {
            $wasVerified = $payment->verify_payments;

            // Si se está VERIFICANDO (false → true), validar con el banco primero
            if (!$wasVerified) {
                $bankValidation = $this->validatePaymentWithBank($payment);

                if (!$bankValidation['success']) {
                    // Si es una petición Inertia, redirigir de vuelta con error
                    if ($request->header('X-Inertia')) {
                        return back()->with('error', $bankValidation['message']);
                    }

                    return response()->json([
                        'success' => false,
                        'message' => $bankValidation['message']
                    ], 422);
                }

                // Validación exitosa, registrar en Wispro si tiene invoice_wispro y no fue registrado antes
                if ($payment->invoice_wispro && !$payment->wispro_registered) {
                    $this->registerPaymentInWispro($payment);
                    $payment->update(['wispro_registered' => true]);
                }
            }

            // Cambiar el estado de verificación
            $payment->verify_payments = !$payment->verify_payments;
            $payment->save();

            $status = $payment->verify_payments ? 'verificado' : 'sin verificar';
            $message = "Pago marcado como {$status}";

            // Si se está verificando el pago (cambiando de false a true)
            if (!$wasVerified && $payment->verify_payments) {
                $appliedInvoices = $this->applyPaymentToInvoices($payment);

                if (count($appliedInvoices) > 0) {
                    $message .= ' y aplicado a ' . count($appliedInvoices) . ' factura(s).';
                }
            }

            // Si es una petición Inertia, redirigir de vuelta
            if ($request->header('X-Inertia')) {
                return back()->with('success', $message);
            }

            // Si es una petición AJAX/JSON normal
            return response()->json([
                'success' => true,
                'verified' => $payment->verify_payments,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            Log::error('Error en toggleVerification: ' . $e->getMessage());

            // Si es una petición Inertia, redirigir de vuelta con error
            if ($request->header('X-Inertia')) {
                return back()->with('error', 'Error al actualizar la verificación del pago: ' . $e->getMessage());
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la verificación del pago'
            ], 500);
        }
    }

    /**
     * Valida un pago con el banco (BNC)
     */
    private function validatePaymentWithBank(Payment $payment): array
    {
        try {
            // Validar que tenga referencia
            if (empty($payment->reference)) {
                return [
                    'success' => false,
                    'message' => 'El pago no tiene referencia para validar.'
                ];
            }

            // Convertir la fecha al formato ISO 8601 requerido por el banco
            $paymentDate = date('Y-m-d\TH:i:s', strtotime($payment->payment_date));

            // Obtener la tasa BCV para calcular el monto en Bs
            $bcvData = BncHelper::getBcvRatesCached();
            $bcvRate = $bcvData['Rate'] ?? null;

            if (!$bcvRate) {
                return [
                    'success' => false,
                    'message' => 'No se pudo obtener la tasa BCV para validar el pago.'
                ];
            }

            // Calcular monto en bolívares
            $amountBs = $payment->amount * $bcvRate;

            // Validar la referencia con el banco
            $result = BncHelper::validateOperationReference(
                $payment->reference,
                $paymentDate,
                $amountBs,
                $payment->bank,
                $payment->phone
            );

            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'No se pudo validar la referencia con el banco. Verifique que los datos sean correctos.'
                ];
            }

            // Validar que el status sea OK
            if (!isset($result['status']) || $result['status'] !== 'OK') {
                return [
                    'success' => false,
                    'message' => $result['message'] ?? 'El banco rechazó la validación. Verifique los datos del pago.'
                ];
            }

            // Obtener datos desencriptados
            $decrypted = $result['decrypted'] ?? null;
            if (!$decrypted) {
                return [
                    'success' => false,
                    'message' => 'No se pudo obtener la información del pago desde el banco.'
                ];
            }

            // Validar si el movimiento existe
            if (!isset($decrypted['MovementExists']) || !$decrypted['MovementExists']) {
                return [
                    'success' => false,
                    'message' => 'No se encontró ningún movimiento con esta referencia en el banco.'
                ];
            }

            // Validar que el monto sea correcto (con un margen de error de 0.01)
            $amountDiff = abs(($decrypted['Amount'] ?? 0) - $amountBs);
            if ($amountDiff > 0.01) {
                return [
                    'success' => false,
                    'message' => 'El monto del pago no coincide con el registrado en el banco. Esperado: ' . number_format($amountBs, 2) . ' Bs, Encontrado: ' . number_format($decrypted['Amount'] ?? 0, 2) . ' Bs'
                ];
            }

            Log::info('Pago validado exitosamente con el banco', [
                'payment_id' => $payment->id,
                'reference' => $payment->reference,
                'amount_bs' => $amountBs
            ]);

            return [
                'success' => true,
                'message' => 'Pago validado exitosamente con el banco.'
            ];

        } catch (\Exception $e) {
            Log::error('Error al validar pago con el banco: ' . $e->getMessage(), [
                'payment_id' => $payment->id,
                'reference' => $payment->reference
            ]);

            return [
                'success' => false,
                'message' => 'Error al validar con el banco: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Registra el pago en Wispro
     */
    private function registerPaymentInWispro(Payment $payment): void
    {
        try {
            $user = $payment->user;

            if (!$user || !$user->id_wispro) {
                Log::warning('No se puede registrar pago en Wispro: usuario sin id_wispro', [
                    'payment_id' => $payment->id,
                    'user_id' => $payment->user_id
                ]);
                return;
            }

            $paymentDate = now()->format('c'); // Formato ISO8601
            $wisproApiService = new WisproApiService();

            $wisproPaymentResponse = $wisproApiService->registerPayment(
                [$payment->invoice_wispro],
                $user->id_wispro,
                $paymentDate,
                $payment->amount,
                "Referencia: {$payment->reference}"
            );

            if ($wisproPaymentResponse['success']) {
                Log::info('Pago registrado exitosamente en Wispro', [
                    'payment_id' => $payment->id,
                    'invoice_wispro' => $payment->invoice_wispro,
                    'client_id' => $user->id_wispro,
                    'response' => $wisproPaymentResponse['data']
                ]);
            } else {
                Log::error('Error al registrar pago en Wispro', [
                    'payment_id' => $payment->id,
                    'invoice_wispro' => $payment->invoice_wispro,
                    'client_id' => $user->id_wispro,
                    'error' => $wisproPaymentResponse['error'] ?? 'Error desconocido',
                    'message' => $wisproPaymentResponse['message'] ?? null
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Excepción al registrar pago en Wispro', [
                'payment_id' => $payment->id,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Aplica un pago verificado a las facturas pendientes del usuario
     */
    private function applyPaymentToInvoices(Payment $payment)
    {
        $user = $payment->user;
        $remainingPayment = $payment->amount; // En USD
        $appliedInvoices = [];

        // Obtener la tasa BCV actual para los cálculos de crédito
        $bcvData = BncHelper::getBcvRatesCached();
        $bcvRate = $bcvData['Rate'] ?? 1;

        // Obtener facturas pendientes del usuario
        $invoices = $user->invoices()
            ->where('status', '!=', 'paid')
            ->orderBy('period')
            ->get();

        if ($invoices->count() > 0) {
            foreach ($invoices as $invoice) {
                $remaining = $invoice->amount_due - $invoice->amount_paid;

                if ($remaining <= 0) continue;

                if ($remainingPayment > 0) {
                    $paymentToApply = min($remaining, $remainingPayment);

                    if ($paymentToApply > 0) {
                        // Crear nuevo registro de pago asociado a la factura
                        Payment::create([
                            'reference' => $payment->reference . ' (Aplicado a Factura)',
                            'user_id' => $user->id,
                            'invoice_id' => $invoice->id,
                            'amount' => $paymentToApply,
                            'id_number' => $payment->id_number,
                            'bank' => $payment->bank,
                            'phone' => $payment->phone,
                            'payment_date' => $payment->payment_date,
                            'image_path' => $payment->image_path,
                            'verify_payments' => true, // Ya verificado
                        ]);

                        $invoice->amount_paid += $paymentToApply;
                        $remainingPayment -= $paymentToApply;

                        // Actualizar estado de la factura
                        $amountDiff = abs($invoice->amount_paid - $invoice->amount_due);
                        if ($amountDiff < 0.01 || $invoice->amount_paid >= $invoice->amount_due) {
                            $invoice->amount_paid = $invoice->amount_due;
                            $invoice->status = 'paid';
                        } elseif ($invoice->amount_paid > 0) {
                            $invoice->status = 'partial';
                        }

                        $invoice->save();

                        $appliedInvoices[] = [
                            'id' => $invoice->id,
                            'period' => $invoice->period ? $invoice->period->format('Y-m') : null,
                            'applied_amount_usd' => $paymentToApply,
                            'applied_amount_bs' => $paymentToApply * $bcvRate,
                            'status' => $invoice->status,
                        ];
                    }
                }

                if ($remainingPayment <= 0) break;
            }
        }

        // Actualizar crédito del usuario con lo que sobró (en bolívares)
        if ($remainingPayment > 0) {
            $remainingPaymentBs = $remainingPayment * $bcvRate;
            $currentCreditBs = $user->credit_balance ?? 0;
            $finalCreditBalance = $currentCreditBs + $remainingPaymentBs;
            User::where('id', $user->id)->update(['credit_balance' => $finalCreditBalance]);
        }

        return $appliedInvoices;
    }

    /**
     * Valida una referencia de pago con el banco
     */
    public function validateReference(Request $request, string $reference)
    {
        try {
            // Validar que la fecha de movimiento y monto sean proporcionados
            $request->validate([
                'payment_date' => 'required|date_format:Y-m-d',
                'expected_amount' => 'required|numeric|min:0.01',
            ]);

            $result = BncHelper::validateOperationReference(
                $reference,
                $request->payment_date,
                $request->expected_amount
            );

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'showReportLink' => true,
                    'message' => 'No se pudo validar la referencia con el banco. ¿Desea reportar su pago manualmente?'
                ]);
            }

            // Validar si el movimiento existe
            if (!$result['MovementExists']) {
                return response()->json([
                    'success' => false,
                    'showReportLink' => true,
                    'message' => 'No se encontró ningún pago con esta referencia. ¿Desea reportar su pago manualmente?'
                ]);
            }

            // Si el movimiento existe, validar que el monto sea correcto (con un margen de error de 0.01)
            $amountDiff = abs($result['Amount'] - $request->expected_amount);
            if ($amountDiff > 0.01) {
                return response()->json([
                    'success' => false,
                    'showReportLink' => true,
                    'message' => 'El monto del pago no coincide con el esperado. ¿Desea reportar su pago manualmente?'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Pago validado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al validar la referencia'
            ], 500);
        }
    }

    /**
     * Valida una referencia de pago y la almacena si es exitosa
     */
    public function validateAndStorePayment(ValidatePaymentRequest $request)
    {
        try {
            $user = Auth::user();

            $reference = $request->reference;
            $amountBs = $request->amount;
            $bank = $request->bank;
            $phoneNumber = $request->phone;

            // Convertir la fecha al formato ISO 8601 requerido por el banco (Y-m-d\TH:i:s)
            $paymentDate = date('Y-m-d\TH:i:s', strtotime($request->payment_date));

            // Validar que la referencia no exista previamente
            if (Payment::where('reference', $reference)->exists()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Esta referencia de pago ya ha sido registrada anteriormente.'
                ], 422);
            }

            // Validar la referencia con el banco
            $result = BncHelper::validateOperationReference(
                $reference,
                $paymentDate,
                $amountBs,
                $bank,
                $phoneNumber
            );

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'showReportLink' => true,
                    'message' => 'No se pudo validar la referencia con el banco. ¿Desea reportar su pago manualmente?'
                ]);
            }

            // Validar que el status sea OK
            if (!isset($result['status']) || $result['status'] !== 'OK') {
                return response()->json([
                    'success' => false,
                    'showReportLink' => true,
                    'message' => $result['message'] ?? 'No se pudo validar el pago con el banco. ¿Desea reportar su pago manualmente?'
                ]);
            }

            // Obtener datos desencriptados
            $decrypted = $result['decrypted'] ?? null;
            if (!$decrypted) {
                return response()->json([
                    'success' => false,
                    'showReportLink' => true,
                    'message' => 'No se pudo obtener la información del pago. ¿Desea reportar su pago manualmente?'
                ]);
            }

            // Validar si el movimiento existe
            if (!isset($decrypted['MovementExists']) || !$decrypted['MovementExists']) {
                return response()->json([
                    'success' => false,
                    'showReportLink' => true,
                    'message' => 'No se encontró ningún pago con esta referencia en la fecha actual. ¿Desea reportar su pago manualmente?'
                ]);
            }

            // Validar que el monto sea correcto (con un margen de error de 0.01)
            $amountDiff = abs(($decrypted['Amount'] ?? 0) - $amountBs);
            if ($amountDiff > 0.01) {
                return response()->json([
                    'success' => false,
                    'showReportLink' => true,
                    'message' => 'El monto del pago no coincide con el esperado. ¿Desea reportar su pago manualmente?'
                ]);
            }

            // Si llegamos aquí, la validación fue exitosa, crear el pago
            $bcvData = BncHelper::getBcvRatesCached();
            $bcvRate = $bcvData['Rate'] ?? null;

            if (!$bcvRate) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo obtener la tasa BCV. Intente nuevamente.'
                ], 500);
            }

            // Convertir el monto de bolívares a dólares
            $amountInUSD = $amountBs / $bcvRate;

            // Crear el pago marcado como verificado
            $payment = Payment::create([
                'reference' => $reference,
                'user_id' => $user->id,
                'invoice_wispro' => $request->invoice_id,
                'amount' => $amountInUSD,
                'id_number' => $user->id_number ?? 'V-00000000',
                'bank' => $bank,
                'phone' => $phoneNumber,
                'payment_date' => $request->payment_date,
                'verify_payments' => true,
                'wispro_registered' => false,
            ]);

            // Registrar el pago en Wispro SIEMPRE que la validación fue exitosa (si vienen los datos necesarios)
            if ($result['status'] === 'OK') {
                try {
                    $paymentDate = now()->format('c'); // Formato ISO8601
                    $wisproApiService = new WisproApiService();
                    $wisproPaymentResponse = $wisproApiService->registerPayment(
                        [$request->invoice_id],
                        $request->client_id,
                        $paymentDate,
                        $amountInUSD,
                        "Referencia {$reference}"
                    );

                    if ($wisproPaymentResponse['success']) {
                        $payment->update(['wispro_registered' => true]);
                        Log::info('VALIDATE P2P: Pago registrado exitosamente en Wispro', [
                            'invoice_id' => $request->invoice_id,
                            'client_id' => $request->client_id,
                            'response' => $wisproPaymentResponse['data']
                        ]);
                    } else {
                        Log::error('VALIDATE P2P: Error al registrar pago en Wispro', [
                            'invoice_id' => $request->invoice_id,
                            'client_id' => $request->client_id,
                            'error' => $wisproPaymentResponse['error'] ?? 'Error desconocido',
                            'message' => $wisproPaymentResponse['message'] ?? null
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('VALIDATE P2P: Excepción al registrar pago en Wispro', [
                        'message' => $e->getMessage()
                    ]);
                }
            }

            // Preparar mensaje de respuesta
            $message = 'Pago verificado y procesado exitosamente.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'payment_id' => $payment->id,
                    'amount_usd' => $amountInUSD,
                    'verified' => true
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error en validateAndStorePayment: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al procesar el pago: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene la lista de bancos desde el BNC
     */
    public function getBanks()
    {
        try {
            // Verificar configuraciones básicas antes de proceder
            $clientId = config('app.bnc.client_id');
            $baseUrl = config('app.bnc.base_url');
            $clientGuid = config('app.bnc.client_guid');
            $masterKey = config('app.bnc.master_key');

            if (empty($clientId) || empty($baseUrl) || empty($clientGuid) || empty($masterKey)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Configuración BNC incompleta'
                ], 500);
            }

            $banks = BncHelper::getBanks();

            if (!$banks) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener la lista de bancos'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $banks
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener la lista de bancos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envía un pago C2P usando el helper BncHelper::sendC2PPayment
     */
    public function sendC2P(SendC2PRequest $request)
    {
        //dd($request->all());
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Usuario no autenticado'
                ], 401);
            }

            $debtorPhoneDigits = $request->debtor_phone;
            $normalizedId = $request->debtor_id;

            Log::info('request', ['request' => $request->all()]);

            // Obtener el terminal desde la configuración
            $terminal = config('app.bnc.terminal');
            if (empty($terminal)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Terminal BNC no configurado. Contacte al administrador.'
                ], 500);
            }

            $result = BncHelper::sendC2PPayment(
                $request->debtor_bank_code,
                $debtorPhoneDigits,
                $normalizedId,
                (float) $request->amount,
                (string) $request->token,
                (string) $terminal
            );

            Log::info('SEND C2P: ResultadoXXX', ['result' => $result]);

            // Si no hay resultado, retornar error
            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo procesar el pago C2P',
                ], 409);
            }

            // Validar que el status sea OK
            if (!isset($result['status']) || $result['status'] !== 'OK') {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'El pago no fue aprobado por el banco',
                ], 409);
            }

            Log::info('SEND C2P: Resultado', ['result' => $result]);

            // Obtener la referencia del value desencriptado
            $decrypted = $result['decrypted'] ?? null;
            if (!$decrypted || !isset($decrypted['Reference'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo obtener la referencia del pago',
                ], 500);
            }

            $reference = $decrypted['Reference'];

            // C2P exitoso - Registrar el pago automáticamente
            $bcvData = BncHelper::getBcvRatesCached();
            $bcvRate = $bcvData['Rate'] ?? null;

            if (!$bcvRate) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se pudo obtener la tasa BCV. Intente nuevamente.'
                ], 500);
            }

            // Convertir el monto de bolívares a dólares
            $amountInUSD = $request->amount / $bcvRate;
            $currentDate = now()->format('Y-m-d');

            // Crear el pago marcado como verificado (C2P validado automáticamente por el banco)
            $payment = Payment::create([
                'reference' => $reference,
                'user_id' => $user->id,
                'invoice_wispro' => $request->invoice_id,
                'amount' => $amountInUSD,
                'id_number' => $normalizedId,
                'bank' => str_pad($request->debtor_bank_code, 4, '0', STR_PAD_LEFT),
                'phone' => $debtorPhoneDigits,
                'payment_date' => $currentDate,
                'verify_payments' => true,
                'wispro_registered' => false,
            ]);

            // Registrar el pago en Wispro SIEMPRE que el C2P fue exitoso (si vienen los datos necesarios)
            if ($result['status'] === 'OK') {
                try {
                    $paymentDate = now()->format('c'); // Formato ISO8601
                    $wisproApiService = new WisproApiService();
                    $wisproPaymentResponse = $wisproApiService->registerPayment(
                        [$request->invoice_id],
                        $request->client_id,
                        $paymentDate,
                        $amountInUSD,
                        "Referencia {$reference}"
                    );

                    if ($wisproPaymentResponse['success']) {
                        $payment->update(['wispro_registered' => true]);
                        Log::info('SEND C2P: Pago registrado exitosamente en Wispro', [
                            'invoice_id' => $request->invoice_id,
                            'client_id' => $request->client_id,
                            'response' => $wisproPaymentResponse['data']
                        ]);
                    } else {
                        Log::error('SEND C2P: Error al registrar pago en Wispro', [
                            'invoice_id' => $request->invoice_id,
                            'client_id' => $request->client_id,
                            'error' => $wisproPaymentResponse['error'] ?? 'Error desconocido',
                            'message' => $wisproPaymentResponse['message'] ?? null
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('SEND C2P: Excepción al registrar pago en Wispro', [
                        'message' => $e->getMessage()
                    ]);
                }
            }

            // Preparar mensaje de respuesta
            $message = 'Pago C2P procesado exitosamente.';

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'payment_id' => $payment->id,
                    'amount_usd' => $amountInUSD,
                    'verified' => true,
                    'bank_response' => $decrypted,
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('SEND C2P: Excepción', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Error al enviar C2P: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exporta la lista de pagos a Excel respetando los filtros actuales.
     */
    public function export(Request $request)
    {
        $search = $request->get('search');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $export = new PaymentsExport($search, $dateFrom, $dateTo);
        $fileName = 'pagos_' . now()->format('Ymd_His') . '.xlsx';
        return $export->download($fileName);
    }
}
