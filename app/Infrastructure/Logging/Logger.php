<?php
declare(strict_types=1);

namespace App\Infrastructure\Logging;

final class Logger
{
    public function __construct(private readonly string $logPath)
    {
        if (!is_dir($logPath)) {
            mkdir($logPath, 0775, true);
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        $file = $this->currentLogFile();
        $this->rotateIfNeeded($file);

        $entry = [
            'ts' => gmdate('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];

        file_put_contents($file, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function currentLogFile(): string
    {
        return rtrim($this->logPath, '/') . '/app-' . date('Y-m-d') . '.log';
    }

    private function rotateIfNeeded(string $file): void
    {
        $maxBytes = 10 * 1024 * 1024;
        if (is_file($file) && filesize($file) > $maxBytes) {
            rename($file, $file . '.' . time());
        }
    }
}
