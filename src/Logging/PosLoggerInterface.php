<?php
declare(strict_types=1);

namespace MPPos\Logging;

interface PosLoggerInterface
{
    public function info(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
}
