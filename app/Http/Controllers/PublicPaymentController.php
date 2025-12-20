<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Payment;
use App\Services\WisproApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PublicPaymentController extends Controller
{
    protected $wisproApi;

    public function __construct(WisproApiService $wisproApi)
    {
        $this->wisproApi = $wisproApi;
    }

    /**
     * Mostrar la página de pago público para un cliente específico
     */
    public function show(string $code)
    {
        try {
            // Buscar usuario por código
            $userData = User::searchByCode($code);

            if (!$userData) {
                return Inertia::render('PublicPayment', [
                    'user' => null,
                    'error' => 'No se encontró ningún cliente con ese código',
                    'wisproInvoices' => []
                ]);
            }

            // Obtener facturas de Wispro si el usuario tiene código
            $wisproInvoices = [];
            if (isset($userData['code']) && !empty($userData['code'])) {
                $invoicesResponse = $this->wisproApi->getInvoicesByCustomId($userData['code'], 1, 100);

                if ($invoicesResponse['success'] && isset($invoicesResponse['data']['data'])) {
                    $wisproInvoices = $invoicesResponse['data']['data'];
                    Log::info('Facturas de Wispro obtenidas', [
                        'code' => $userData['code'],
                        'count' => count($wisproInvoices)
                    ]);
                }
            }

            //dd($wisproInvoices);

            return Inertia::render('PublicPayment', [
                'user' => $userData,
                'error' => null,
                'wisproInvoices' => $wisproInvoices
            ]);

        } catch (\Exception $e) {
            Log::error('Error en pago público: ' . $e->getMessage());
            return Inertia::render('PublicPayment', [
                'user' => null,
                'error' => 'Error al cargar la información del cliente',
                'wisproInvoices' => []
            ]);
        }
    }

    /**
     * Procesar el pago público
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'reference' => 'required|string|max:50',
                'nationality' => 'required|string|in:V,E,J',
                'id_number' => 'required|string|max:20',
                'bank' => 'required|string|max:100',
                'phone' => 'required|string|max:20',
                'amount' => 'required|numeric|min:0.01',
                'payment_date' => 'required|date',
                'image' => 'nullable|image|max:2048',
            ]);

            // Concatenar nacionalidad con número de cédula
            $fullIdNumber = $validated['nationality'] . '-' . $validated['id_number'];

            // Calcular el monto en USD usando la tasa BCV
            $bcvRate = \App\Helpers\BncHelper::getBcvRatesCached()['Rate'] ?? 1;
            $amountUsd = $validated['amount'] / $bcvRate;

            // Preparar datos del pago
            $paymentData = [
                'user_id' => $validated['user_id'],
                'reference' => $validated['reference'],
                'id_number' => $fullIdNumber,
                'bank' => $validated['bank'],
                'phone' => $validated['phone'],
                'amount' => $amountUsd,
                'payment_date' => $validated['payment_date'],
                'verify_payments' => false, // Los pagos públicos requieren verificación
            ];

            // Manejar la imagen si existe
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
                $imagePath = $image->storeAs('payments', $imageName, 'public');
                $paymentData['image_path'] = $imagePath;
            }

            // Crear el pago
            Payment::create($paymentData);

            return redirect()->back()->with('success', 'Pago registrado exitosamente. Será verificado por nuestro equipo.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Error al procesar pago público: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error al procesar el pago. Por favor, intente nuevamente.');
        }
    }
}

