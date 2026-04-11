<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;

class BdvIpg2Payment extends Model
{
    protected $fillable = [
        'payment_id',
        'user_id',
        'invoice_ids',
        'cellphone',
        'amount',
        'reference',
        'status',
        'approved_at',
        'expires_at',
    ];

    protected $casts = [
        'invoice_ids' => 'array',
        'status'      => PaymentStatus::class,
        'approved_at' => 'datetime',
        'expires_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }

    public function markApproved(): void
    {
        $this->update([
            'status'      => PaymentStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function markRejected(): void
    {
        $this->update(['status' => PaymentStatus::Rejected]);
    }
}
