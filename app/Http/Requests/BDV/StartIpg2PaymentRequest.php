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
            'idLetter'   => ['required', 'in:V,E,J'],
            'idNumber'   => ['required', 'digits_between:6,9'],
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
            'idLetter'   => 'Nacionalidad',
            'idNumber'   => 'Número de cédula',
            'email'      => 'Correo electrónico',
            'cellphone'  => 'Teléfono',
            'amount'     => 'Monto',
            'invoice_ids' => 'IDs de las facturas',
            'invoice_ids.*' => 'ID de la factura',
        ];
    }
}
