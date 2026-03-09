<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

}
