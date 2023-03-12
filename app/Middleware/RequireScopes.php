<?php

declare(strict_types=1);

namespace App\Middleware;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use LeanPHP\Http\Problem;

class RequireScopes
{
    private array $requiredScopes;

    /**
     * Create a new RequireScopes middleware instance.
     *
     * @param string $scopes Comma-separated list of required scopes
     */
    public function __construct(string $scopes)
    {
        $this->requiredScopes = array_map('trim', explode(',', $scopes));
    }

    /**
     * Handle the request and check for required scopes.
     *
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        // Check if the request is authenticated
        if (!$request->isAuthenticated()) {
            return Problem::unauthorized('This endpoint requires authentication')
                ->header('WWW-Authenticate', 'Bearer realm="API"');
        }

        // Get the scopes from the JWT claims
        $tokenScopes = $request->claim('scopes', []);

        if (!is_array($tokenScopes)) {
            return Problem::forbidden('Token does not contain valid scopes');
        }

        // Check if all required scopes are present
        foreach ($this->requiredScopes as $requiredScope) {
            if (!in_array($requiredScope, $tokenScopes, true)) {
                return Problem::forbidden("Required scope '$requiredScope' is missing");
            }
        }

        // All required scopes are present, continue
        return $next($request);
    }

    /**
     * Create a middleware instance with the given scopes.
     * This is a helper method for easier usage in route definitions.
     *
     * @param string $scopes Comma-separated list of required scopes
     * @return self
     */
    public static function check(string $scopes): self
    {
        return new self($scopes);
    }
}
