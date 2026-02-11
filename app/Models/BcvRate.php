<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BcvRate extends Model
{
    protected $fillable = [
        'rate',
        'date',
        'updated_by',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'date' => 'date',
    ];

    /**
     * Obtener la última tasa BCV registrada
     */
    public static function getLatestRate(): ?array
    {
        $latest = self::latest('created_at')->first();

        if (!$latest) {
            return null;
        }

        return [
            'Rate' => floatval($latest->rate),
            'Date' => $latest->date->format('Y-m-d'),
            'source' => 'database',
        ];
    }
}
