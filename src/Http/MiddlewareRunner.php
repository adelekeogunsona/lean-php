<?php

declare(strict_types=1);

namespace LeanPHP\Http;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;

class MiddlewareRunner
{
    private array $middleware = [];

    /**
     * Add middleware to the pipeline.
     */
    public function add(object $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Set the entire middleware stack.
     */
    public function setMiddleware(array $middleware): void
    {
        $this->middleware = $middleware;
    }

    /**
     * Handle the request through the middleware pipeline.
     */
    public function handle(Request $request, callable $coreHandler): Response
    {
        // Create the middleware chain
        $next = $coreHandler;

        // Build the chain in reverse order (last middleware first)
        for ($i = count($this->middleware) - 1; $i >= 0; $i--) {
            $middleware = $this->middleware[$i];
            $next = fn(Request $req) => $middleware->handle($req, $next);
        }

        return $next($request);
    }
}
