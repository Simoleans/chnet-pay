<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceWispro extends Model
{
    protected $table = 'invoices_wispro';

    protected $fillable = [
        'wispro_id',
        'contract_id',
        'invoicing_firm_id',
        'kind_invoice',
        'invoice_number',
        'period_from',
        'period_to',
        'issued_at',
        'first_due_date',
        'second_due_date',
        'state',
        'amount',
        'gross_amount',
        'tax_amount',
        'discount_amount',
        'net_amount',
        'balance',
        'invoicing_firm_company_name',
        'client_name',
        'client_national_identification_number',
        'client_email',
        'client_phone',
        'wispro_created_at',
        'wispro_updated_at',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'issued_at' => 'datetime',
        'first_due_date' => 'date',
        'second_due_date' => 'date',
        'amount' => 'decimal:2',
        'gross_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'wispro_created_at' => 'datetime',
        'wispro_updated_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItemWispro::class, 'invoice_wispro_id');
    }

    public function paymentLinks(): HasMany
    {
        return $this->hasMany(PaymentInvoiceWispro::class, 'invoice_wispro_id');
    }
}
