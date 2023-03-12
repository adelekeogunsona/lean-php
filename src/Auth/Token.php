<?php

declare(strict_types=1);

namespace LeanPHP\Auth;

use InvalidArgumentException;
use RuntimeException;

class Token
{
    private const ALGORITHM = 'HS256';
    private const TIME_LEEWAY = 30; // 30 seconds leeway for time-based claims

    /**
     * Issue a new JWT token with the given claims
     *
     * @param array $claims The claims to include in the token
     * @param int|null $ttl Time to live in seconds (uses AUTH_TOKEN_TTL if null)
     * @return string The JWT token
     */
    public static function issue(array $claims, ?int $ttl = null): string
    {
        $currentKid = \env_string('AUTH_JWT_CURRENT_KID');
        if (!$currentKid) {
            throw new RuntimeException('AUTH_JWT_CURRENT_KID environment variable not set');
        }

        $keys = self::parseJwtKeys();
        if (!isset($keys[$currentKid])) {
            throw new RuntimeException("Current JWT key '$currentKid' not found in AUTH_JWT_KEYS");
        }

        $now = time();
        $ttl = $ttl ?? \env_int('AUTH_TOKEN_TTL', 900);

        // Build JWT payload with standard claims
        $payload = array_merge($claims, [
            'iat' => $now,                    // Issued at
            'nbf' => $now,                    // Not before
            'exp' => $now + $ttl,             // Expires at
            'jti' => self::generateJti(),     // JWT ID (unique identifier)
        ]);

        // Build JWT header
        $header = [
            'typ' => 'JWT',
            'alg' => self::ALGORITHM,
            'kid' => $currentKid,
        ];

        // Create JWT
        $headerEncoded = self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = self::sign("$headerEncoded.$payloadEncoded", $keys[$currentKid]);

        return "$headerEncoded.$payloadEncoded.$signature";
    }

    /**
     * Verify and decode a JWT token
     *
     * @param string $jwt The JWT token to verify
     * @return array The decoded claims
     * @throws InvalidArgumentException If the token is invalid
     */
    public static function verify(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Invalid JWT format');
        }

        [$headerEncoded, $payloadEncoded, $signature] = $parts;

        // Decode header
        try {
            $header = json_decode(self::base64UrlDecode($headerEncoded), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('Invalid JWT header: ' . $e->getMessage());
        }

        // Verify algorithm
        if (!isset($header['alg']) || $header['alg'] !== self::ALGORITHM) {
            throw new InvalidArgumentException('Unsupported or missing algorithm');
        }

        // Get key ID
        if (!isset($header['kid'])) {
            throw new InvalidArgumentException('Missing key ID in header');
        }

        $kid = $header['kid'];
        $keys = self::parseJwtKeys();

        if (!isset($keys[$kid])) {
            throw new InvalidArgumentException("Unknown key ID: $kid");
        }

        // Verify signature
        $expectedSignature = self::sign("$headerEncoded.$payloadEncoded", $keys[$kid]);
        if (!hash_equals($signature, $expectedSignature)) {
            throw new InvalidArgumentException('Invalid signature');
        }

        // Decode payload
        try {
            $payload = json_decode(self::base64UrlDecode($payloadEncoded), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('Invalid JWT payload: ' . $e->getMessage());
        }

        // Verify time-based claims
        $now = time();

        if (isset($payload['nbf']) && $payload['nbf'] > ($now + self::TIME_LEEWAY)) {
            throw new InvalidArgumentException('Token not yet valid');
        }

        if (isset($payload['exp']) && $payload['exp'] < ($now - self::TIME_LEEWAY)) {
            throw new InvalidArgumentException('Token has expired');
        }

        if (isset($payload['iat']) && $payload['iat'] > ($now + self::TIME_LEEWAY)) {
            throw new InvalidArgumentException('Token issued in the future');
        }

        return $payload;
    }

    /**
     * Parse JWT keys from environment variable
     *
     * @return array<string, string> Array of kid => secret pairs
     */
    private static function parseJwtKeys(): array
    {
        $keysString = \env_string('AUTH_JWT_KEYS');
        if (!$keysString) {
            throw new RuntimeException('AUTH_JWT_KEYS environment variable not set');
        }

        $keys = [];
        $keyPairs = explode(',', $keysString);

        foreach ($keyPairs as $keyPair) {
            $parts = explode(':', $keyPair, 2);
            if (count($parts) !== 2) {
                throw new RuntimeException("Invalid JWT key format: $keyPair");
            }

            [$kid, $secret] = $parts;
            $kid = trim($kid);
            $secret = trim($secret);

            if (empty($kid) || empty($secret)) {
                throw new RuntimeException("Empty key ID or secret in: $keyPair");
            }

            // Decode base64url secret
            $keys[$kid] = self::base64UrlDecode($secret);
        }

        return $keys;
    }

    /**
     * Create HMAC signature for JWT
     *
     * @param string $data The data to sign
     * @param string $secret The secret key
     * @return string Base64url-encoded signature
     */
    private static function sign(string $data, string $secret): string
    {
        $signature = hash_hmac('sha256', $data, $secret, true);
        return self::base64UrlEncode($signature);
    }

    /**
     * Generate a unique JWT ID
     *
     * @return string
     */
    private static function generateJti(): string
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(16));
        }

        // Fallback for older PHP versions
        return uniqid('', true);
    }

    /**
     * Base64url encode
     *
     * @param string $data
     * @return string
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64url decode
     *
     * @param string $data
     * @return string
     */
    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }
}
