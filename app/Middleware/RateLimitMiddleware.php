<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Infrastructure\Cache\CacheInterface;
use App\Interface\Http\Request;
use App\Interface\Http\Response;

final class RateLimitMiddleware
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $maxPerMinute
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        $key = sprintf('ratelimit:%s:%s:%s', date('YmdHi'), $request->ip, $request->path);
        $count = $this->cache->increment($key, 120);

        if ($count > $this->maxPerMinute) {
            return Response::json(false, [], [['code' => 'rate_limited', 'message' => 'Too many requests']], 429);
        }

        return $next($request);
    }
}
