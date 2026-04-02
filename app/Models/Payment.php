<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Support\WisproInvoiceIds;

class Payment extends Model
{
    protected $fillable = [
        'user_id',
        'invoice_id',
        'reference',
        'id_number',
        'bank',
        'phone',
        'payment_date',
        'invoice_wispro',
        'amount',
        'image_path',
        'verify_payments',
        'wispro_registered',
        'type_bank',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'verify_payments' => 'boolean',
        'wispro_registered' => 'boolean',
    ];

    const TYPE_BANK_BNC = 'bnc';
    const TYPE_BANK_BDV = 'bdv';


    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function safeCreate(string $reference, User $user, array $invoiceIds, float $amountUsd, string $idNumber, string $bank, string $typeBank, $phone = null): self
    {
        return self::create([
            'reference'         => $reference,
            'user_id'           => $user?->id,
            'invoice_wispro'    => WisproInvoiceIds::encode($invoiceIds),
            'amount'            => $amountUsd,
            'id_number'         => $idNumber,
            'bank'              => $bank,
            'phone'             => $phone,
            'payment_date'      => now()->format('Y-m-d'),
            'verify_payments'   => true,
            'wispro_registered' => false,
            'type_bank'         => $typeBank,
        ]);
    }

}
