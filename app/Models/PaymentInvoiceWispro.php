<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentInvoiceWispro extends Model
{
    protected $table = 'payment_invoice_wispro';

    protected $fillable = [
        'wispro_transaction_id',
        'payment_wispro_id',
        'invoice_wispro_id',
        'invoice_id',
        'invoice_number',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentWispro::class, 'payment_wispro_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(InvoiceWispro::class, 'invoice_wispro_id');
    }
}
