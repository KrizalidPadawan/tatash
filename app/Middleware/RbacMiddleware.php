<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Infrastructure\Repositories\UserRepository;
use App\Interface\Http\Request;
use App\Interface\Http\Response;

final class RbacMiddleware
{
    /** @var UserRepository|callable */
    private mixed $users;

    public function __construct(
        UserRepository|callable $users,
        private readonly string $permissionCode
    ) {
        $this->users = $users;
    }

    public function handle(Request $request, callable $next): Response
    {
        $auth = $request->attributes['auth'] ?? null;
        if (!$auth) {
            return Response::json(false, [], [['code' => 'unauthorized', 'message' => 'No auth context']], 401);
        }

        $roleId = (int) ($auth['role_id'] ?? 0);
        $repo = $this->users instanceof UserRepository ? $this->users : ($this->users)();
        if (!$repo->hasPermission($roleId, $this->permissionCode)) {
            return Response::json(false, [], [['code' => 'forbidden', 'message' => 'Insufficient permissions']], 403);
        }

        return $next($request);
    }
}
