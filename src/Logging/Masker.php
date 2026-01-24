<?php
declare(strict_types=1);

namespace MPPos\Logging;

final class Masker
{
    private const SENSITIVE_KEYS = [
        'card_number',
        'KK_No',
        'cvv',
        'KK_CVC',
        'password',
        'Pass',
        'Islem_Hash',
    ];

    public static function mask(array $data): array
    {
        foreach ($data as $k => $v) {
            if (in_array($k, self::SENSITIVE_KEYS, true)) {
                $data[$k] = '***MASKED***';
            } elseif (is_array($v)) {
                $data[$k] = self::mask($v);
            }
        }
        return $data;
    }
}
