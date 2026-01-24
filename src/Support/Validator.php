<?php
declare(strict_types=1);

namespace MPPos\Support;

use MPPos\Exceptions\ValidationException;

final class Validator
{
    public static function required(array $fields): void
    {
        foreach ($fields as $name => $value) {
            if ($value === null || $value === '') {
                throw new ValidationException("Field '{$name}' is required");
            }
        }
    }
}
