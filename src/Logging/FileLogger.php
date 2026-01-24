<?php
declare(strict_types=1);

namespace MPPos\Logging;

final class FileLogger implements PosLoggerInterface
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = rtrim($path, '/');
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $line = json_encode([
            'time'    => date('c'),
            'level'   => $level,
            'message' => $message,
            'context' => Masker::mask($context),
        ], JSON_UNESCAPED_UNICODE);

        file_put_contents(
            $this->path . '/mppos.log',
            $line . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
