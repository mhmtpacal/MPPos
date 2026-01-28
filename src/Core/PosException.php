<?php
declare(strict_types=1);

namespace MPPos\Core;

use RuntimeException;

final class PosException extends RuntimeException
{
    public static function missing(string $key): self
    {
        return new self("Missing field: {$key}");
    }

    public static function missingAccount(string $key): self
    {
        return new self("Missing account field: {$key}");
    }
}
