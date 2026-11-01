<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Simple HTTP router with parameterized URL pattern support.
 *
 * Supports {param} placeholders in route patterns that get extracted
 * and passed as arguments to the controller method.
 *
 * Usage:
 *   $router = new Router();
 *   $router->get('models/{id}', 'ModelController', 'show');
 *   $router->dispatch('GET', 'models/42');
 *
 * @package App\Support
 */
class Router
{
    /**
     * Registered routes grouped by HTTP method.
     *
     * @var array<string, array<int, array{pattern: string, regex: string, params: string[], controller: string, action: string}>>
     */
    private array $routes = [];

    /**
     * Register a route for a given HTTP method.
     *
     * @param string $method     HTTP method (GET, POST, DELETE, etc.).
     * @param string $pattern    URL pattern with optional {param} placeholders.
     * @param string $controller Controller class name (without namespace prefix).
     * @param string $action     Method name to call on the controller.
     * @return void
     */
    public function addRoute(string $method, string $pattern, string $controller, string $action): void
    {
        $method = strtoupper($method);
        $pattern = trim($pattern, '/');

        // Extract parameter names and build regex
        $paramNames = [];
        $regexPattern = preg_replace_callback('/\{([a-zA-Z_]+)\}/', function (array $matches) use (&$paramNames): string {
            $paramNames[] = $matches[1];
            return '([^/]+)';
        }, $pattern);

        $regex = '#^' . $regexPattern . '$#';

        $this->routes[$method][] = [
            'pattern'    => $pattern,
            'regex'      => $regex,
            'params'     => $paramNames,
            'controller' => $controller,
            'action'     => $action,
        ];
    }

    /**
     * Register a GET route.
     *
     * @param string $pattern    URL pattern.
     * @param string $controller Controller class name.
     * @param string $action     Method name.
     * @return void
     */
    public function get(string $pattern, string $controller, string $action): void
    {
        $this->addRoute('GET', $pattern, $controller, $action);
    }

    /**
     * Register a POST route.
     *
     * @param string $pattern    URL pattern.
     * @param string $controller Controller class name.
     * @param string $action     Method name.
     * @return void
     */
    public function post(string $pattern, string $controller, string $action): void
    {
        $this->addRoute('POST', $pattern, $controller, $action);
    }

    /**
     * Register a DELETE route.
     *
     * @param string $pattern    URL pattern.
     * @param string $controller Controller class name.
     * @param string $action     Method name.
     * @return void
     */
    public function delete(string $pattern, string $controller, string $action): void
    {
        $this->addRoute('DELETE', $pattern, $controller, $action);
    }

    /**
     * Dispatch an incoming request to the matching route.
     *
     * Resolves the controller class from the App\Controllers namespace,
     * instantiates it, and calls the action method with extracted parameters.
     *
     * Sends a 404 JSON response if no route matches.
     * Sends a 405 JSON response if the route exists but the method is not allowed.
     *
     * @param string $method The HTTP request method.
     * @param string $uri    The request URI (without query string or api prefix).
     * @return void
     */
    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $uri = trim($uri, '/');

        // Check for matching route with correct method
        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $route) {
                if (preg_match($route['regex'], $uri, $matches)) {
                    array_shift($matches); // Remove the full match

                    // Build named parameters
                    $params = [];
                    foreach ($route['params'] as $index => $name) {
                        $params[$name] = $matches[$index] ?? null;
                    }

                    $this->callController($route['controller'], $route['action'], $params);
                    return;
                }
            }
        }

        // Check if the route exists under a different method (405 vs 404)
        $allowedMethods = $this->getAllowedMethods($uri);

        if (!empty($allowedMethods)) {
            // Route exists but method not allowed
            header('Allow: ' . implode(', ', $allowedMethods));
            Response::error(
                'METHOD_NOT_ALLOWED',
                "Method {$method} is not allowed for this endpoint. Allowed: " . implode(', ', $allowedMethods),
                405
            );
        }

        // No matching route at all
        Response::error(
            'NOT_FOUND',
            "Endpoint not found: {$method} {$uri}",
            404
        );
    }

    /**
     * Get the list of HTTP methods allowed for a given URI.
     *
     * @param string $uri The request URI.
     * @return string[] Array of allowed HTTP method names.
     */
    private function getAllowedMethods(string $uri): array
    {
        $allowed = [];

        foreach ($this->routes as $routeMethod => $routes) {
            foreach ($routes as $route) {
                if (preg_match($route['regex'], $uri)) {
                    $allowed[] = $routeMethod;
                    break;
                }
            }
        }

        return $allowed;
    }

    /**
     * Instantiate a controller and call the specified action method.
     *
     * @param string $controllerName Controller class name (without namespace).
     * @param string $action         Method name to invoke.
     * @param array  $params         Named parameters to pass to the method.
     * @return void
     */
    private function callController(string $controllerName, string $action, array $params): void
    {
        $className = "App\\Controllers\\{$controllerName}";

        if (!class_exists($className)) {
            Logger::error("Controller class not found: {$className}");
            Response::error(
                'INTERNAL_ERROR',
                'Internal server error: controller not found.',
                500
            );
        }

        $controller = new $className();

        if (!method_exists($controller, $action)) {
            Logger::error("Action method not found: {$className}::{$action}");
            Response::error(
                'INTERNAL_ERROR',
                'Internal server error: action not found.',
                500
            );
        }

        $controller->{$action}(...array_values($params));
    }
}
