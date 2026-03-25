<?php

namespace App\Http\Requests\BDV;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class VerifyP2PRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalizar = fn(string $tlf): string =>
            preg_replace('/^\+?58/', '0', preg_replace('/\D/', '', $tlf));

        $merge = [];

        if ($this->has('telefonoPagador')) {
            $merge['telefonoPagador'] = $normalizar((string) $this->telefonoPagador);
        }

        if ($this->has('telefonoDestino')) {
            $merge['telefonoDestino'] = $normalizar((string) $this->telefonoDestino);
        }

        if ($merge) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'cedulaPagador'   => ['required', 'string'],
            'telefonoPagador' => ['required', 'string'],
            'telefonoDestino' => ['required', 'string'],
            'referencia'      => ['required', 'string'],
            'fechaPago'       => ['required', 'string'],
            'importe'         => ['required', 'string'],
            'bancoOrigen'     => ['required', 'string'],
            'reqCed'          => ['sometimes', 'boolean'],
            'invoice_id'      => ['sometimes', 'nullable', 'string'],
            'invoice_ids'     => ['sometimes', 'nullable', 'array'],
            'invoice_ids.*'   => ['string'],
            'client_id'       => ['sometimes', 'nullable', 'string'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'error'   => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
