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
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
            'regex' => $this->compileRoute($path),
        ];
    }

    /**
     * Compile a route path into a regex pattern.
     */
    private function compileRoute(string $path): string
    {
        // For now, handle static routes only
        // Route parameters will be added in a future phase
        return '#^' . preg_quote($path, '#') . '$#';
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
            if (preg_match($route['regex'], $path)) {
                if ($route['method'] === $method) {
                    $matchedRoutes[] = $route;
                }
                $availableMethods[] = $route['method'];
            }
        }

        // Auto-support HEAD requests by mapping to GET
        if ($method === 'HEAD') {
            foreach ($this->routes as $route) {
                if ($route['method'] === 'GET' && preg_match($route['regex'], $path)) {
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

        // Call the handler
        $handler = $route['handler'];

        if (is_array($handler)) {
            // Controller@method format
            [$controller, $method] = $handler;
            if (is_string($controller)) {
                $controller = new $controller();
            }
            $response = $controller->$method($request);
        } else {
            // Callable
            $response = $handler($request);
        }

        // Ensure we have a Response object
        if (!$response instanceof Response) {
            throw new \InvalidArgumentException('Route handler must return a Response object');
        }

        // For HEAD requests, remove the body
        if ($request->method() === 'HEAD') {
            return $response->withoutBody();
        }

        return $response;
    }
}
