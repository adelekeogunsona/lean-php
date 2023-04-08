<?php

declare(strict_types=1);

namespace App\Middleware;

use LeanPHP\Http\Request;
use LeanPHP\Http\Response;

class ETag
{
    /**
     * Handle the request and add ETag support for JSON responses.
     *
     * @param Request $request
     * @param callable $next
     * @return Response
     */
    public function handle(Request $request, callable $next): Response
    {
        // Process the request to get the response first
        $response = $next($request);

        // Only apply ETag for GET requests with 200 status and JSON content
        if (!$this->shouldApplyETag($request, $response)) {
            return $response;
        }

        // Generate ETag from response body
        $etag = $this->generateETag($response->getBody());

        // Check If-None-Match header
        $ifNoneMatch = $request->header('if-none-match');
        if ($ifNoneMatch !== null && $this->etagMatches($ifNoneMatch, $etag)) {
            // Return 304 Not Modified with ETag header but no body
            return Response::noContent()
                ->status(304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'private, max-age=0, must-revalidate');
        }

        // Add ETag header to the response
        return $response->header('ETag', $etag);
    }

    /**
     * Determine if ETag should be applied to this request/response.
     *
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    private function shouldApplyETag(Request $request, Response $response): bool
    {
        // Only GET requests
        if ($request->method() !== 'GET') {
            return false;
        }

        // Only 200 OK responses
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        // Only JSON responses
        $contentType = $response->getHeader('content-type');
        if ($contentType === null || !str_starts_with($contentType, 'application/json')) {
            return false;
        }

        // Must have a non-empty body
        return !empty($response->getBody());
    }

    /**
     * Generate an ETag from response content.
     *
     * @param string $content
     * @return string
     */
    private function generateETag(string $content): string
    {
        // SHA256 hash, then base64url encode
        $hash = hash('sha256', $content, true);
        $base64url = $this->base64UrlEncode($hash);

        // Wrap in quotes as per HTTP specification
        return '"' . $base64url . '"';
    }

    /**
     * Encode data using base64url encoding.
     *
     * @param string $data
     * @return string
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Check if the If-None-Match header matches our ETag.
     *
     * @param string $ifNoneMatch
     * @param string $etag
     * @return bool
     */
    private function etagMatches(string $ifNoneMatch, string $etag): bool
    {
        // Handle wildcard
        if ($ifNoneMatch === '*') {
            return true;
        }

        // Handle comma-separated list of ETags
        $etags = array_map('trim', explode(',', $ifNoneMatch));

        return in_array($etag, $etags, true);
    }
}
