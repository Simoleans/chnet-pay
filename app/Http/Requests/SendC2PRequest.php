<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class SendC2PRequest extends FormRequest
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
     * Normaliza teléfono y cédula antes de validar, reemplazando los valores originales
     */
    protected function prepareForValidation(): void
    {
        // Normalizar teléfono: quitar todo lo que no sea dígito y reemplazar el valor original
        if ($this->has('debtor_phone')) {
            $phoneDigits = preg_replace('/\D/', '', (string) $this->debtor_phone);
            $this->merge([
                'debtor_phone' => $phoneDigits
            ]);
        }

        // Normalizar cédula: convertir a mayúsculas y quitar guiones/puntos, reemplazando el valor original
        if ($this->has('debtor_id')) {
            $normalizedId = strtoupper(preg_replace('/[^VE0-9]/', '', $this->debtor_id));
            $this->merge([
                'debtor_id' => $normalizedId
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'debtor_bank_code' => 'required|string',
            'token' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'debtor_id' => ['required', 'string', 'max:20', 'regex:/^[VEve]-?[0-9]+$/'],
            'debtor_phone' => ['required', 'string', 'max:20'],
            'invoice_id' => 'required|string',
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
            if ($this->has('debtor_phone')) {
                $phoneDigits = $this->debtor_phone;
                if (!preg_match('/^58\d{10}$/', $phoneDigits)) {
                    $validator->errors()->add(
                        'debtor_phone',
                        'Formato de teléfono inválido. Use 58XXXXXXXXXX (sin +, espacios ni guiones)'
                    );
                }
            }

            // Validar formato de cédula normalizada (V/E + dígitos)
            if ($this->has('debtor_id')) {
                $normalizedId = $this->debtor_id;
                if (!preg_match('/^[VE][0-9]+$/', $normalizedId)) {
                    $validator->errors()->add(
                        'debtor_id',
                        'Formato de cédula inválido. Debe ser V00000000 o E00000000'
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
}
