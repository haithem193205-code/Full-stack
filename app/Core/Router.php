<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Router
 *
 * Lightweight REST router with route-group middleware support.
 * Routes are registered as: METHOD, PATH (with {param} placeholders), [Controller, method], middleware[]
 */
final class Router
{
    /** @var array<int, array{method:string, path:string, action:array, middleware:array}> */
    private array $routes = [];

    private string $groupPrefix = '';
    private array $groupMiddleware = [];

    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix .= $prefix;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function get(string $path, array $action, array $middleware = []): void
    {
        $this->add('GET', $path, $action, $middleware);
    }

    public function post(string $path, array $action, array $middleware = []): void
    {
        $this->add('POST', $path, $action, $middleware);
    }

    public function put(string $path, array $action, array $middleware = []): void
    {
        $this->add('PUT', $path, $action, $middleware);
    }

    public function delete(string $path, array $action, array $middleware = []): void
    {
        $this->add('DELETE', $path, $action, $middleware);
    }

    private function add(string $method, string $path, array $action, array $middleware): void
    {
        $this->routes[] = [
            'method'     => $method,
            'path'       => rtrim($this->groupPrefix . $path, '/') ?: '/',
            'action'     => $action,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = parse_url($uri, PHP_URL_PATH) ?? '/';
        $uri = rtrim($uri, '/') ?: '/';

        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = preg_replace('#\{[a-zA-Z_][a-zA-Z0-9_]*\}#', '([a-zA-Z0-9_\-]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                $params = array_values($matches);

                $request = new Request();

                // Run middleware chain (each may mutate $request, e.g. attach ->user)
                foreach ($route['middleware'] as $middlewareClass) {
                    $mw = new $middlewareClass();
                    $mw->handle($request);
                }

                [$controllerClass, $methodName] = $route['action'];
                $controller = new $controllerClass();
                $controller->$methodName($request, ...$params);
                return;
            }
        }

        Response::notFound('Route not found');
    }
}
