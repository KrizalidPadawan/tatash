<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Infrastructure\Security\JwtHandler;
use App\Interface\Http\Request;
use App\Interface\Http\Response;

final class AuthMiddleware
{
    public function __construct(private readonly JwtHandler $jwt) {}

    public function handle(Request $request, callable $next): Response
    {
        $authHeader = $request->header('authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', (string) $authHeader, $m)) {
            return Response::json(false, [], [['code' => 'unauthorized', 'message' => 'Missing bearer token']], 401);
        }

        try {
            $payload = $this->jwt->decode($m[1]);
        } catch (\Throwable $e) {
            return Response::json(false, [], [['code' => 'invalid_token', 'message' => $e->getMessage()]], 401);
        }

        $request->attributes['auth'] = $payload;
        $request->attributes['tenant_id'] = (int) $payload['tenant_id'];

        return $next($request);
    }
}
