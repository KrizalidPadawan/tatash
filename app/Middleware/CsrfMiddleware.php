<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Interface\Http\Request;
use App\Interface\Http\Response;

final class CsrfMiddleware
{
    public function __construct(private readonly string $csrfHeader) {}

    public function handle(Request $request, callable $next): Response
    {
        if ($request->method === 'GET') {
            return $next($request);
        }

        if (str_starts_with($request->path, '/api/')) {
            return $next($request);
        }

        $token = $request->header(strtolower($this->csrfHeader), '');
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (!$token || !hash_equals($sessionToken, $token)) {
            return Response::json(false, [], [['code' => 'csrf_invalid', 'message' => 'Invalid CSRF token']], 419);
        }

        return $next($request);
    }
}
