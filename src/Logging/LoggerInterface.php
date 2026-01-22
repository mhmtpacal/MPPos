<?php
declare(strict_types=1);

namespace MPPos\Logging;

interface LoggerInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void;
}

