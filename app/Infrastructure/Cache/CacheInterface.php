<?php
declare(strict_types=1);

namespace App\Infrastructure\Cache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, int $ttlSeconds): void;
    public function increment(string $key, int $ttlSeconds): int;
    public function delete(string $key): void;
}
