<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Infrastructure\Repositories\UserRepository;
use App\Interface\Http\Request;
use App\Interface\Http\Response;

final class RbacMiddleware
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly string $permissionCode
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        $auth = $request->attributes['auth'] ?? null;
        if (!$auth) {
            return Response::json(false, [], [['code' => 'unauthorized', 'message' => 'No auth context']], 401);
        }

        $roleId = (int) ($auth['role_id'] ?? 0);
        if (!$this->users->hasPermission($roleId, $this->permissionCode)) {
            return Response::json(false, [], [['code' => 'forbidden', 'message' => 'Insufficient permissions']], 403);
        }

        return $next($request);
    }
}
