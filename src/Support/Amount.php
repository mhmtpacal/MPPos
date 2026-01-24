<?php
declare(strict_types=1);

namespace MPPos\Support;

use MPPos\Exceptions\ValidationException;

final class Amount
{
    public static function fromInt(int $amount): int
    {
        if ($amount <= 0) {
            throw new ValidationException('Amount must be greater than zero');
        }
        return $amount;
    }
}
