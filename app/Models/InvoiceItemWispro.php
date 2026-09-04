<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItemWispro extends Model
{
    protected $table = 'invoice_items_wispro';

    protected $fillable = [
        'wispro_id',
        'invoice_wispro_id',
        'invoice_wispro_uuid',
        'description',
        'product_code',
        'quantity',
        'amount',
        'gross_amount',
        'tax_amount',
        'net_amount',
        'discount_amount',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(InvoiceWispro::class, 'invoice_wispro_id');
    }
}
