<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentWispro extends Model
{
    protected $table = 'payments_wispro';

    protected $fillable = [
        'wispro_id',
        'public_id',
        'client_id',
        'client_name',
        'client_public_id',
        'amount',
        'credit_amount',
        'payment_date',
        'comment',
        'transaction_kind',
        'transaction_code',
        'state',
        'download',
        'name_user',
        'name_collector',
        'wispro_created_at',
        'wispro_updated_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'payment_date' => 'datetime',
        'download' => 'boolean',
        'wispro_created_at' => 'datetime',
        'wispro_updated_at' => 'datetime',
    ];

    public function invoiceLinks(): HasMany
    {
        return $this->hasMany(PaymentInvoiceWispro::class, 'payment_wispro_id');
    }
}
