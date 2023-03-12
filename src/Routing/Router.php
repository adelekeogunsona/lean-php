<?php

declare(strict_types=1);

namespace LeanPHP\Routing;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use LeanPHP\Http\Problem;

class Router
{
    private array $routes = [];
    private array $globalMiddleware = [];
    private array $groupStack = [];

    /**
     * Register a GET route.
     */
    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Register a PATCH route.
     */
    public function patch(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Register an OPTIONS route.
     */
    public function options(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('OPTIONS', $path, $handler, $middleware);
    }

    /**
     * Register a HEAD route.
     */
    public function head(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('HEAD', $path, $handler, $middleware);
    }

    /**
     * Add a route to the routing table.
     */
    private function addRoute(string $method, string $path, callable|array $handler, array $middleware): void
    {
        // Apply group prefixes and middleware
        $fullPath = $this->getFullPath($path);
        $fullMiddleware = $this->getFullMiddleware($middleware);

        $compiled = $this->compileRoute($fullPath);

        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'handler' => $handler,
            'middleware' => $fullMiddleware,
            'regex' => $compiled['regex'],
            'params' => $compiled['params'],
        ];
    }

    /**
     * Compile a route path into a regex pattern.
     */
    private function compileRoute(string $path): array
    {
        $params = [];
        $regex = $path;

        // Find route parameters like {id} or {id:\d+}
        $regex = preg_replace_callback('/\{([^}]+)\}/', function ($matches) use (&$params) {
            $param = $matches[1];

            // Check if parameter has a constraint
            if (str_contains($param, ':')) {
                [$name, $constraint] = explode(':', $param, 2);
                $params[] = $name;
                return '(' . $constraint . ')';
            }

            // Default constraint for parameters without explicit constraint
            $params[] = $param;
            return '([^/]+)';
        }, $regex);

        // Escape other regex characters and wrap in delimiters
        $regex = '#^' . $regex . '$#';

        return [
            'regex' => $regex,
            'params' => $params,
        ];
    }

    /**
     * Set global middleware that runs for all routes.
     */
    public function setGlobalMiddleware(array $middleware): void
    {
        $this->globalMiddleware = $middleware;
    }

    /**
     * Dispatch a request and return a response.
     */
    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        $matchedRoutes = [];
        $availableMethods = [];

        // Find matching routes
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                if ($route['method'] === $method) {
                    // Extract route parameters
                    $params = [];
                    for ($i = 1; $i < count($matches); $i++) {
                        if (isset($route['params'][$i - 1])) {
                            $params[$route['params'][$i - 1]] = $matches[$i];
                        }
                    }

                    $route['matched_params'] = $params;
                    $matchedRoutes[] = $route;
                }
                $availableMethods[] = $route['method'];
            }
        }

        // Auto-support HEAD requests by mapping to GET
        if ($method === 'HEAD') {
            foreach ($this->routes as $route) {
                if ($route['method'] === 'GET' && preg_match($route['regex'], $path, $matches)) {
                    // Extract route parameters for HEAD request too
                    $params = [];
                    for ($i = 1; $i < count($matches); $i++) {
                        if (isset($route['params'][$i - 1])) {
                            $params[$route['params'][$i - 1]] = $matches[$i];
                        }
                    }

                    $route['matched_params'] = $params;
                    $matchedRoutes[] = $route;
                    break;
                }
            }
        }

        if (empty($matchedRoutes)) {
            if (!empty($availableMethods)) {
                // Method not allowed - return 405 with Allow header
                $uniqueMethods = array_unique($availableMethods);

                // Auto-add HEAD if GET is available
                if (in_array('GET', $uniqueMethods) && !in_array('HEAD', $uniqueMethods)) {
                    $uniqueMethods[] = 'HEAD';
                }

                return Problem::make(405, 'Method Not Allowed')
                    ->header('Allow', implode(', ', $uniqueMethods));
            }

            // Route not found - return 404
            return Problem::make(404, 'Not Found', "Route {$path} not found", '/problems/not-found');
        }

        $route = $matchedRoutes[0];

        // Set route parameters in the request
        if (isset($route['matched_params'])) {
            $request->setParams($route['matched_params']);
        }

        // Run global middleware first, then route-specific middleware and handler
        $response = $this->runMiddleware($this->globalMiddleware, $route, $request);

        if (!$response instanceof Response) {
            throw new \InvalidArgumentException('Route handler must return a Response object');
        }

        // For HEAD requests, remove the body
        if ($request->method() === 'HEAD') {
            return $response->withoutBody();
        }

        return $response;
    }

    /**
     * Create a route group with common prefix and middleware.
     */
    public function group(string $prefix, array $attributes, callable $callback): void
    {
        $this->groupStack[] = [
            'prefix' => $prefix,
            'middleware' => $attributes['middleware'] ?? [],
        ];

        $callback($this);

        array_pop($this->groupStack);
    }

    /**
     * Get the full path including group prefixes.
     */
    private function getFullPath(string $path): string
    {
        $prefix = '';

        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'];
        }

        return $prefix . $path;
    }

    /**
     * Get the full middleware array including group middleware.
     */
    private function getFullMiddleware(array $routeMiddleware): array
    {
        $middleware = [];

        // Add group middleware
        foreach ($this->groupStack as $group) {
            $middleware = array_merge($middleware, $group['middleware']);
        }

        // Add route-specific middleware
        $middleware = array_merge($middleware, $routeMiddleware);

        return $middleware;
    }

    /**
     * Run global middleware, route-specific middleware, and handler in sequence.
     */
    private function runMiddleware(array $globalMiddleware, array $route, Request $request): Response
    {
        $routeMiddleware = $route['middleware'];
        $handler = $route['handler'];

        // Combine global middleware with route middleware (global runs first)
        $allMiddleware = array_merge($globalMiddleware, $routeMiddleware);

        // If no middleware at all, call handler directly
        if (empty($allMiddleware)) {
            return $this->callHandler($handler, $request);
        }

        // Create middleware pipeline starting with the handler
        $next = fn(Request $req) => $this->callHandler($handler, $req);

        // Build the chain in reverse order (so they execute in forward order)
        for ($i = count($allMiddleware) - 1; $i >= 0; $i--) {
            $middlewareInstance = $allMiddleware[$i];

            // Instantiate middleware if it's a string class name
            if (is_string($middlewareInstance)) {
                $middlewareInstance = new $middlewareInstance();
            }

            $next = fn(Request $req) => $middlewareInstance->handle($req, $next);
        }

        return $next($request);
    }

    /**
     * Call the route handler.
     */
    private function callHandler(callable|array $handler, Request $request): Response
    {
        if (is_array($handler)) {
            // Controller@method format
            [$controller, $method] = $handler;
            if (is_string($controller)) {
                $controller = new $controller();
            }
            return $controller->$method($request);
        }

        // Callable
        return $handler($request);
    }
}
