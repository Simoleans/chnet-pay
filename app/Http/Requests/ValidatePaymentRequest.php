<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ValidatePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en el controlador
    }

    /**
     * Prepare the data for validation.
     * Normaliza teléfono antes de validar, reemplazando el valor original
     */
    protected function prepareForValidation(): void
    {
        // Normalizar teléfono: quitar todo lo que no sea dígito
        if ($this->has('phone')) {
            $phoneDigits = preg_replace('/\D/', '', (string) $this->phone);

            // Si comienza con 0, quitarlo (0412 -> 412)
            if (strlen($phoneDigits) > 0 && $phoneDigits[0] === '0') {
                $phoneDigits = substr($phoneDigits, 1);
            }

            // Agregar el prefijo 58 si no lo tiene (4120355541 -> 584120355541)
            if (strlen($phoneDigits) === 10 && !str_starts_with($phoneDigits, '58')) {
                $phoneDigits = '58' . $phoneDigits;
            }

            $this->merge([
                'phone' => $phoneDigits
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'reference' => ['required', 'string', 'regex:/^\d{6}$/'],
            'amount' => 'required|numeric|min:0.01',
            'bank' => 'required|string',
            'phone' => ['required', 'string', 'max:20'],
            'payment_date' => 'required|date',
            'invoice_id' => 'required_without:invoice_ids|nullable|string',
            'invoice_ids' => 'required_without:invoice_id|nullable|array|min:1',
            'invoice_ids.*' => 'required|string|distinct',
            'client_id' => 'required|string',
        ];
    }

    /**
     * Configure the validator instance.
     * Valida los datos normalizados
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // Validar formato de teléfono normalizado (58 + 10 dígitos)
            if ($this->has('phone')) {
                $phoneDigits = $this->phone;
                // Debe ser 58XXXXXXXXXX (12 dígitos total)
                if (!preg_match('/^58\d{10}$/', $phoneDigits)) {
                    $validator->errors()->add(
                        'phone',
                        'Formato de teléfono inválido. Use 58XXXXXXXXXX (sin +, espacios ni guiones)'
                    );
                }
            }
        });
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'error' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422)
        );
    }

    public function messages(): array
    {
        return [
            'reference.regex' => 'La referencia debe tener exactamente 6 números.',
            'client_id.required' => 'El cliente es obligatorio para procesar el pago.',

            'invoice_id.required_without' => 'Debe indicar al menos una factura.',
            'invoice_ids.required_without' => 'Debe indicar al menos una factura.',
            'invoice_ids.array' => 'Las facturas deben enviarse como una lista.',
            'invoice_ids.min' => 'Debe indicar al menos una factura.',
            'invoice_ids.*.required' => 'Cada factura debe tener un valor.',
            'invoice_ids.*.distinct' => 'No debe repetir facturas.',
        ];
    }
}
