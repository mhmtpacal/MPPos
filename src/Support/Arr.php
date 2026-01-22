<?php
declare(strict_types=1);

namespace MPPos\Support;

final class Arr
{
    public static function pick(array $arr, array $keys, string $default = ''): string
    {
        foreach ($keys as $k) {
            $v = self::getPath($arr, $k);
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }
        return $default;
    }

    /**
     * @return mixed
     */
    private static function getPath(array $arr, string $path): mixed
    {
        $v = $arr;
        foreach (explode('.', $path) as $p) {
            if (!is_array($v) || !array_key_exists($p, $v)) {
                return null;
            }
            $v = $v[$p];
        }
        return $v;
    }
}
