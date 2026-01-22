<?php
declare(strict_types=1);

namespace MPPos\Logging;

final class ErrorLogLogger implements LoggerInterface
{
    public function log(string $level, string $message, array $context = []): void
    {
        $payload = [
            'ts' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        // Keep it JSON so it plays well with log collectors.
        error_log((string)json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

