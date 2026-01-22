<?php
declare(strict_types=1);

namespace MPPos\Support;

use MPPos\Exceptions\ValidationException;

final class Amount
{
    /**
     * @return int amount in cents (kuruş)
     */
    public static function toCents(int|string|float $amount): int
    {
        if (is_int($amount)) {
            if ($amount < 0) throw new ValidationException('Amount cannot be negative');
            return $amount;
        }

        $s = (string)$amount;
        $s = str_replace(['₺','TL',' ', "\t", "\n", "\r"], '', $s);
        // Turkish format: 1.234,50  -> 1234.50
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);

        if ($s === '' || !is_numeric($s)) {
            throw new ValidationException('Invalid amount');
        }

        $val = (float)$s;
        if ($val < 0) throw new ValidationException('Amount cannot be negative');

        return (int) round($val * 100);
    }
}
