<?php

namespace App\Support;

/**
 * Codifica y decodifica IDs de factura Wispro en payments.invoice_wispro
 * (un UUID o varios separados por coma).
 */
class WisproInvoiceIds
{
    /**
     * @param  array<int|string>  $ids
     */
    public static function encode(array $ids): ?string
    {
        $ids = array_values(array_filter(array_map('strval', $ids)));
        if (count($ids) === 0) {
            return null;
        }
        if (count($ids) === 1) {
            return $ids[0];
        }

        return implode(',', $ids);
    }

    /**
     * @return list<string>
     */
    public static function parse(?string $stored): array
    {
        if ($stored === null || $stored === '') {
            return [];
        }
        $trim = trim($stored);
        if (str_starts_with($trim, '[')) {
            $decoded = json_decode($trim, true);

            return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
        }
        if (str_contains($trim, ',')) {
            return array_values(array_filter(array_map('trim', explode(',', $trim))));
        }

        return [$trim];
    }
}
