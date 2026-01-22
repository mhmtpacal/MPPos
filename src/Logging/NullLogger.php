<?php
declare(strict_types=1);

namespace MPPos\Logging;

final class NullLogger implements LoggerInterface
{
    public function log(string $level, string $message, array $context = []): void
    {
        // Intentionally no-op.
    }
}

