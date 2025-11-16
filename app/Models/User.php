<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address',
        'zone',
        'code',
        'id_number',
        'id_wispro', // ID del cliente en Wispro
        'status',
        'role',
        'credit_balance',
    ];

    protected $casts = [
        'status' => 'boolean',
        'role' => 'integer',
    ];

    /**
     * Valores por defecto para atributos
     */
    protected $attributes = [
        'role' => 1, // Por defecto es admin/trabajador
        'status' => true, // Por defecto activo
    ];

    //append due
    protected $appends = ['due'];


    public function getDueAttribute()
    {
        // Obtener todas las facturas no pagadas completamente
        $invoices = $this->invoices()
            ->where('status', '!=', 'paid')
            ->sum(DB::raw('amount_due - amount_paid'));

        return $invoices;
    }

    //scope active
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    //scope not admin
    public function scopeNotAdmin($query)
    {
        return $query->where('role', '!=', 1);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }


    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getCurrentInvoiceData()
    {
        $currentPeriod = Carbon::now()->startOfMonth();

        $invoice = $this->invoices()
            ->with(['payments'])
            ->where('period', $currentPeriod)
            ->first();

        if (!$invoice) {
            $previous_unpaid = $this->invoices()
                ->where('status', '!=', 'paid')
                ->where('period', '<', $currentPeriod)
                ->sum(DB::raw('amount_due - amount_paid'));

            return [
                'invoice_exists' => false,
                'debt' => $previous_unpaid,
                'total_due' => $previous_unpaid,
            ];
        }

        return [
            'invoice_exists' => true,
            'invoice_id' => $invoice->id,
            'period' => $invoice->period ? $invoice->period->format('Y-m') : null,
            'status' => $invoice->status,
            'amount_due' => $invoice->amount_due,
            'amount_paid' => $invoice->amount_paid,
            'pending_amount' => $invoice->amount_due - $invoice->amount_paid,
            'payments' => $invoice->payments->map(function ($p) {
                return [
                    'amount' => $p->amount,
                    'bank' => $p->bank,
                    'phone' => $p->phone,
                    'payment_date' => $p->payment_date ? $p->payment_date->format('Y-m-d') : null,
                ];
            }),
            'is_paid' => $invoice->status === 'paid',
            'is_partial' => $invoice->status === 'partial',
            'is_pending' => $invoice->status === 'pending',
        ];
    }

    /**
     * Busca un usuario por código y retorna información de deuda
     */
    public static function searchByCode(string $code): ?array
    {
        $user = self::where('code', $code)
            ->where('status', true)
            ->first();

        if (!$user) {
            return null;
        }

        $invoiceData = $user->getCurrentInvoiceData();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'code' => $user->code,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'zone' => $user->zone, // Ahora es un campo de texto directo
            'plan' => null, // Ya no hay relación con planes locales
            'credit_balance' => $user->credit_balance ?? 0, // En bolívares
            'total_debt' => $user->due,
            'invoice_data' => $invoiceData,
        ];
    }

    /**
     * Busca un usuario local por su ID de Wispro
     */
    public static function findByWisproId(string $wisproId): ?self
    {
        return self::where('id_wispro', $wisproId)->first();
    }

    /**
     * Verifica si un cliente de Wispro existe en BD local
     */
    public static function existsInLocal(string $wisproId): bool
    {
        return self::where('id_wispro', $wisproId)->exists();
    }
}
