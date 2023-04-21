<?php

declare(strict_types=1);

namespace App\Middleware;

use InvalidArgumentException;
use LeanPHP\Auth\Token;
use LeanPHP\Http\Request;
use LeanPHP\Http\Response;
use LeanPHP\Http\Problem;
use LeanPHP\Validation\ValidationException;

class AuthBearer
{
    /**
     * Handle the request and verify Bearer token authentication.
     *
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        // Get the bearer token from the request
        $bearerToken = $request->bearerToken();

        if ($bearerToken === null) {
            return $this->unauthorized('Missing or invalid Authorization header');
        }

        try {
            // Verify the JWT token
            $claims = Token::verify($bearerToken);

            // Attach the claims to the request
            $request->setClaims($claims);

            // Continue to the next middleware/controller
            return $next($request);

        } catch (ValidationException $e) {
            // Re-throw ValidationException so it's handled by ErrorHandler
            throw $e;
        } catch (InvalidArgumentException $e) {
            return $this->unauthorized('Invalid token: ' . $e->getMessage());
        } catch (\Exception $e) {
            return $this->unauthorized('Token verification failed');
        }
    }

    /**
     * Create a 401 Unauthorized response with WWW-Authenticate header.
     *
     * @param string $message
     * @return Response
     */
    private function unauthorized(string $message): Response
    {
        return Problem::unauthorized($message)
            ->header('WWW-Authenticate', 'Bearer realm="API"');
    }
}
