<?php
declare(strict_types=1);

namespace App\Interface\Http;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => rtrim($path, '/') ?: '/',
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method || $route['path'] !== $request->path) {
                continue;
            }

            $handler = $route['handler'];
            $stack = array_reverse($route['middlewares']);
            $next = static fn (Request $r) => self::invoke($handler, $r);

            foreach ($stack as $mw) {
                $next = static fn (Request $r) => $mw->handle($r, $next);
            }

            return $next($request);
        }

        return Response::json(false, [], [['code' => 'not_found', 'message' => 'Endpoint not found']], 404);
    }

    private static function invoke(callable|array $handler, Request $request): Response
    {
        if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
            $controller = new $handler[0]();
            $method = $handler[1];
            return $controller->$method($request);
        }

        return $handler($request);
    }
}
