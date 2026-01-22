<?php
declare(strict_types=1);

namespace MPPos\Support;

final class Arr
{
    public static function pick(array $arr, array $keys, string $default = ''): string
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $arr) && is_string($arr[$k]) && $arr[$k] !== '') {
                return $arr[$k];
            }
        }
        return $default;
    }
}
