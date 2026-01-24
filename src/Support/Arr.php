<?php
declare(strict_types=1);

namespace MPPos\Support;

use MPPos\Exceptions\ValidationException;

final class Arr
{
    public static function get(array $data, string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, $data)) {
            if ($default !== null) return $default;
            throw new ValidationException("Missing field: {$key}");
        }

        return $data[$key];
    }
}
