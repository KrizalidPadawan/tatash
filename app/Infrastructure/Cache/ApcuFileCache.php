<?php
declare(strict_types=1);

namespace App\Infrastructure\Cache;

final class ApcuFileCache implements CacheInterface
{
    public function __construct(private readonly string $cachePath)
    {
        if (!is_dir($cachePath)) {
            @mkdir($cachePath, 0775, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $k = $this->normalize($key);
        if ($this->shouldUseApcu()) {
            $ok = false;
            $value = apcu_fetch($k, $ok);
            if ($ok) {
                return $value;
            }
        }

        $file = $this->fileFor($k);
        if (!is_file($file)) {
            return $default;
        }

        $payload = json_decode((string) file_get_contents($file), true);
        if (!is_array($payload) || time() > (int) ($payload['expires_at'] ?? 0)) {
            @unlink($file);
            return $default;
        }

        return $payload['value'] ?? $default;
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        $k = $this->normalize($key);
        if ($this->shouldUseApcu() && apcu_store($k, $value, $ttlSeconds)) {
            return;
        }

        $file = $this->fileFor($k);
        $this->ensureCacheDirectory();
        file_put_contents($file, json_encode([
            'expires_at' => time() + $ttlSeconds,
            'value' => $value,
        ], JSON_THROW_ON_ERROR), LOCK_EX);
    }

    public function increment(string $key, int $ttlSeconds): int
    {
        $k = $this->normalize($key);
        if ($this->shouldUseApcu()) {
            $ok = false;
            $value = apcu_inc($k, 1, $ok, $ttlSeconds);
            if (!$ok) {
                if (apcu_store($k, 1, $ttlSeconds)) {
                    return 1;
                }
            } else {
                return (int) $value;
            }
        }

        $current = (int) $this->get($k, 0);
        $current++;
        $this->set($k, $current, $ttlSeconds);
        return $current;
    }

    public function delete(string $key): void
    {
        $k = $this->normalize($key);
        if ($this->shouldUseApcu()) {
            apcu_delete($k);
        }
        @unlink($this->fileFor($k));
    }

    public function diagnostics(): array
    {
        $this->ensureCacheDirectory();

        return [
            'apcu_enabled' => $this->shouldUseApcu(),
            'path' => $this->cachePath,
            'directory_exists' => is_dir($this->cachePath),
            'directory_writable' => is_dir($this->cachePath) && is_writable($this->cachePath),
        ];
    }

    private function normalize(string $key): string
    {
        return 'saas_' . preg_replace('/[^a-zA-Z0-9_\\-]/', '_', $key);
    }

    private function fileFor(string $key): string
    {
        return rtrim($this->cachePath, '/') . '/' . sha1($key) . '.cache.json';
    }

    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cachePath)) {
            @mkdir($this->cachePath, 0775, true);
        }
    }

    private function shouldUseApcu(): bool
    {
        if (!function_exists('apcu_store') || !function_exists('apcu_fetch')) {
            return false;
        }

        if (function_exists('apcu_enabled')) {
            return apcu_enabled();
        }

        return filter_var((string) ini_get('apc.enabled'), FILTER_VALIDATE_BOOL);
    }
}
