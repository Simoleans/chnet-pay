<?php

namespace App\Http\Requests\BDV;

use Illuminate\Foundation\Http\FormRequest;

class StartIpg2PaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'idLetter'   => ['required', 'in:V,E'],
            'idNumber'   => ['required', 'digits_between:6,9'],
            'rifLetter'  => ['nullable', 'required_with:rifNumber', 'in:J'],
            'rifNumber'  => ['nullable', 'required_with:rifLetter', 'digits_between:7,10'],
            'email'      => ['required', 'email:rfc,dns'],
            'cellphone'  => ['required', 'digits:11', 'regex:/^04[0-9]{9}$/'],
            'amount'     => ['required', 'numeric', 'min:0.01'],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['required', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'idLetter'   => 'Letra de la cédula del representante',
            'idNumber'   => 'Número de cédula del representante',
            'rifLetter'  => 'Letra del RIF',
            'rifNumber'  => 'Número del RIF',
            'email'      => 'Correo electrónico',
            'cellphone'  => 'Teléfono',
            'amount'     => 'Monto',
            'invoice_ids' => 'IDs de las facturas',
            'invoice_ids.*' => 'ID de la factura',
        ];
    }
}
