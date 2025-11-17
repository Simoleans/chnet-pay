<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    // Tipos de identificación
    private const ID_TYPE_ABONADO = 0;
    private const ID_TYPE_CEDULA_RIF = 1;

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
        $rules = [
            'id_type' => ['required', 'integer', 'in:0,1'],
            'id_number' => ['required', 'numeric'],
            'password' => ['required', 'string'],
        ];

        // Solo validar nacionalidad si es cédula/RIF
        if (request()->input('id_type') == self::ID_TYPE_CEDULA_RIF) {
            $rules['nationality'] = ['required', 'string', 'in:V,E,J'];
        }

        // Solo validar captcha si está configurado
        if (config('services.recaptcha.site_key') && config('services.recaptcha.secret_key')) {
            $rules['g-recaptcha-response'] = ['required', function ($attribute, $value, $fail) {
                $this->validateRecaptcha($value, $fail);
            }];
        }

        return $rules;
    }

    /**
     * Validate reCAPTCHA response
     */
    private function validateRecaptcha($value, $fail): void
    {
        $secretKey = config('services.recaptcha.secret_key');

        if (empty($secretKey)) {
            $fail('La configuración de reCAPTCHA no está disponible.');
            return;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secretKey,
            'response' => $value,
            'remoteip' => request()->ip(),
        ]);

        $result = $response->json();

        if (!$result['success']) {
            $fail('Por favor, complete el captcha correctamente.');
        }
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $idNumber = $this->validated()['id_number'];
        $password = $this->validated()['password'];
        $idType = $this->validated()['id_type'];

        // Construir credenciales según el tipo de identificación usando match expression
        $credentials = match ($idType) {
            self::ID_TYPE_ABONADO => [
                'code' => $idNumber,
                'password' => $password
            ],
            self::ID_TYPE_CEDULA_RIF => [
                'id_number' => $this->validated()['nationality'] . '-' . $idNumber,
                'password' => $password
            ],
            default => throw ValidationException::withMessages([
                'id_type' => 'Tipo de identificación no válido',
            ])
        };

        // Intentar autenticación con las credenciales construidas
        if (Auth::attempt($credentials, !empty($this->remember))) {
            RateLimiter::clear($this->throttleKey());
            return;
        }

        // Si falla, registrar intento fallido
        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'id_number' => 'Credenciales incorrectas',
        ]);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        /** @var string $idNumber */
        $idNumber = $this->validated()['id_number'] ?? '';
        return Str::transliterate(Str::lower($idNumber).'|'.request()->ip());
    }
}
