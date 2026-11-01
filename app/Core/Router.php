<?php
namespace App\Core;

class Router
{
    private array $routes = [];
    private string $prefix = '';
    private array $groupMiddlewares = [];

    public function group(string $prefix, array $middlewares, callable $callback): void
    {
        $prevPrefix = $this->prefix;
        $prevMiddlewares = $this->groupMiddlewares;

        $this->prefix = rtrim($prevPrefix, '/') . '/' . trim($prefix, '/');
        $this->groupMiddlewares = array_merge($prevMiddlewares, $middlewares);

        $callback($this);

        $this->prefix = $prevPrefix;
        $this->groupMiddlewares = $prevMiddlewares;
    }

    public function add(string $method, string $path, mixed $handler, array $middlewares = []): void
    {
        $fullPath = rtrim($this->prefix, '/') . '/' . trim($path, '/');
        $fullPath = '/' . trim($fullPath, '/');

        $this->routes[] = [
            'method'      => strtoupper($method),
            'pattern'     => $this->buildPattern($fullPath),
            'path'        => $fullPath,
            'handler'     => $handler,
            'middlewares' => array_merge($this->groupMiddlewares, $middlewares)
        ];
    }

    public function get(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $path, $handler, $middlewares);
    }

    private function buildPattern(string $path): string
    {
        // Replace {param} with regex named capture group
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Handle CORS preflight
        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                // Filter named parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Run route middlewares
                foreach ($route['middlewares'] as $middleware) {
                    if (is_string($middleware)) {
                        $middlewareInstance = new $middleware();
                        $middlewareInstance->handle($request);
                    } elseif (is_callable($middleware)) {
                        $middleware($request);
                    }
                }

                // Call handler
                $handler = $route['handler'];
                if (is_array($handler) && count($handler) === 2) {
                    [$class, $action] = $handler;
                    $controller = new $class();
                    $controller->$action($request, $params);
                    return;
                }

                if (is_callable($handler)) {
                    $handler($request, $params);
                    return;
                }
            }
        }

        Response::error('ROUTE_NOT_FOUND', "Endpoint {$method} {$path} not found.", 404);
    }
}
