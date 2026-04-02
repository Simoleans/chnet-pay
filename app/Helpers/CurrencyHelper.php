<?php
namespace App\Helpers;

use App\Models\BcvRate;
use RuntimeException;

class CurrencyHelper
{
    public static function bsToUsd(float $amountBs): float
    {
        $bcvData = BcvRate::getLatestRate();
        $bcvRate = $bcvData['Rate'] ?? null;

        if (!$bcvRate) {
            throw new RuntimeException('No se pudo obtener la tasa BCV.');
        }

        return $amountBs / $bcvRate;
    }

    public static function usdToBs(float $amountUsd): float
    {
        $bcvData = BcvRate::getLatestRate();
        $bcvRate = $bcvData['Rate'] ?? null;

        if (!$bcvRate) {
            throw new RuntimeException('No se pudo obtener la tasa BCV.');
        }

        return $amountUsd * $bcvRate;
    }
}
