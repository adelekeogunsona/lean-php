<?php

declare(strict_types=1);

use LeanPHP\Auth\Token;
use PHPUnit\Framework\TestCase;

class TokenTest extends TestCase
{
    protected function setUp(): void
    {
        // Set up test JWT configuration
        putenv('AUTH_JWT_CURRENT_KID=main');
        putenv('AUTH_JWT_KEYS=main:dGVzdC1zZWNyZXQtbWFpbi1rZXk,old:dGVzdC1zZWNyZXQtb2xkLWtleQ');
        putenv('AUTH_TOKEN_TTL=900');
    }

    protected function tearDown(): void
    {
        // Clean up environment - use false to unset
        putenv('AUTH_JWT_CURRENT_KID=');
        putenv('AUTH_JWT_KEYS=');
        putenv('AUTH_TOKEN_TTL=');
    }

    public function test_can_issue_and_verify_token(): void
    {
        $claims = [
            'sub' => '123',
            'scopes' => ['users.read', 'users.write'],
            'email' => 'test@example.com'
        ];

        $token = Token::issue($claims);
        $this->assertIsString($token);

        // JWT should have 3 parts separated by dots
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);

        // Verify the token
        $decoded = Token::verify($token);

        // Check that our custom claims are present
        $this->assertEquals('123', $decoded['sub']);
        $this->assertEquals(['users.read', 'users.write'], $decoded['scopes']);
        $this->assertEquals('test@example.com', $decoded['email']);

        // Check that standard claims were added
        $this->assertArrayHasKey('iat', $decoded);
        $this->assertArrayHasKey('nbf', $decoded);
        $this->assertArrayHasKey('exp', $decoded);
        $this->assertArrayHasKey('jti', $decoded);

        // Check time claims are reasonable
        $now = time();
        $this->assertLessThanOrEqual($now, $decoded['iat']);
        $this->assertLessThanOrEqual($now, $decoded['nbf']);
        $this->assertGreaterThan($now, $decoded['exp']);
    }

    public function test_can_issue_token_with_custom_ttl(): void
    {
        $claims = ['sub' => '123'];
        $customTtl = 1800; // 30 minutes

        $token = Token::issue($claims, $customTtl);
        $decoded = Token::verify($token);

        $expectedExpiry = $decoded['iat'] + $customTtl;
        $this->assertEquals($expectedExpiry, $decoded['exp']);
    }

    public function test_expired_token_throws_exception(): void
    {
        // Create a token with negative TTL beyond the leeway period (30s + buffer)
        $token = Token::issue(['sub' => '123'], -60);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Token has expired');
        Token::verify($token);
    }

    public function test_token_validation_includes_time_checks(): void
    {
        // This test verifies that time-based validation is present
        // More comprehensive time-based testing would require mocking time()
        $token = Token::issue(['sub' => '123']);
        $decoded = Token::verify($token);

        // Verify that time-based claims are set correctly
        $now = time();
        $this->assertLessThanOrEqual($now + 1, $decoded['iat']);
        $this->assertLessThanOrEqual($now + 1, $decoded['nbf']);
        $this->assertGreaterThan($now, $decoded['exp']);
    }

    public function test_invalid_signature_throws_exception(): void
    {
        $token = Token::issue(['sub' => '123']);

        // Tamper with the signature
        $parts = explode('.', $token);
        $parts[2] = 'invalid-signature';
        $tamperedToken = implode('.', $parts);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid signature');
        Token::verify($tamperedToken);
    }

    public function test_can_verify_token_with_different_key(): void
    {
        // Issue token with main key
        $token = Token::issue(['sub' => '123']);

        // Switch to old key for issuing
        putenv('AUTH_JWT_CURRENT_KID=old');
        $tokenWithOldKey = Token::issue(['sub' => '456']);

        // Both tokens should be verifiable (key rotation scenario)
        $mainDecoded = Token::verify($token);
        $oldDecoded = Token::verify($tokenWithOldKey);

        $this->assertEquals('123', $mainDecoded['sub']);
        $this->assertEquals('456', $oldDecoded['sub']);
    }

    public function test_invalid_jwt_format_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid JWT format');
        Token::verify('invalid.jwt');
    }

    public function test_missing_key_id_throws_exception(): void
    {
        // Create a token without kid in header
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $payload = ['sub' => '123'];

        $headerEncoded = rtrim(strtr(base64_encode(json_encode($header, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $payloadEncoded = rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        $invalidToken = "$headerEncoded.$payloadEncoded.fake-signature";

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing key ID in header');
        Token::verify($invalidToken);
    }

    public function test_unknown_key_id_throws_exception(): void
    {
        // Create token with unknown kid
        $header = ['typ' => 'JWT', 'alg' => 'HS256', 'kid' => 'unknown'];
        $payload = ['sub' => '123'];

        $headerEncoded = rtrim(strtr(base64_encode(json_encode($header, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $payloadEncoded = rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        $invalidToken = "$headerEncoded.$payloadEncoded.fake-signature";

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown key ID: unknown');
        Token::verify($invalidToken);
    }

    public function test_unsupported_algorithm_throws_exception(): void
    {
        // Create token with wrong algorithm
        $header = ['typ' => 'JWT', 'alg' => 'RS256', 'kid' => 'main'];
        $payload = ['sub' => '123'];

        $headerEncoded = rtrim(strtr(base64_encode(json_encode($header, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $payloadEncoded = rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');

        $invalidToken = "$headerEncoded.$payloadEncoded.fake-signature";

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported or missing algorithm');
        Token::verify($invalidToken);
    }

    public function test_missing_current_kid_throws_exception(): void
    {
        // Temporarily unset the current KID
        $originalKid = $_ENV['AUTH_JWT_CURRENT_KID'] ?? null;
        $originalServerKid = $_SERVER['AUTH_JWT_CURRENT_KID'] ?? null;

        unset($_ENV['AUTH_JWT_CURRENT_KID']);
        unset($_SERVER['AUTH_JWT_CURRENT_KID']);
        putenv('AUTH_JWT_CURRENT_KID');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('AUTH_JWT_CURRENT_KID environment variable not set');
            Token::issue(['sub' => '123']);
        } finally {
            // Restore original values
            if ($originalKid !== null) {
                $_ENV['AUTH_JWT_CURRENT_KID'] = $originalKid;
                putenv("AUTH_JWT_CURRENT_KID=$originalKid");
            }
            if ($originalServerKid !== null) {
                $_SERVER['AUTH_JWT_CURRENT_KID'] = $originalServerKid;
            }
        }
    }

    public function test_missing_jwt_keys_throws_exception(): void
    {
        // Temporarily unset JWT keys
        $originalKeys = $_ENV['AUTH_JWT_KEYS'] ?? null;
        $originalServerKeys = $_SERVER['AUTH_JWT_KEYS'] ?? null;

        unset($_ENV['AUTH_JWT_KEYS']);
        unset($_SERVER['AUTH_JWT_KEYS']);
        putenv('AUTH_JWT_KEYS');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('AUTH_JWT_KEYS environment variable not set');
            Token::issue(['sub' => '123']);
        } finally {
            // Restore original values
            if ($originalKeys !== null) {
                $_ENV['AUTH_JWT_KEYS'] = $originalKeys;
                putenv("AUTH_JWT_KEYS=$originalKeys");
            }
            if ($originalServerKeys !== null) {
                $_SERVER['AUTH_JWT_KEYS'] = $originalServerKeys;
            }
        }
    }

    public function test_current_kid_not_in_keys_throws_exception(): void
    {
        // Temporarily set current KID to nonexistent value
        $originalKid = $_ENV['AUTH_JWT_CURRENT_KID'] ?? null;
        $originalServerKid = $_SERVER['AUTH_JWT_CURRENT_KID'] ?? null;

        $_ENV['AUTH_JWT_CURRENT_KID'] = 'nonexistent';
        $_SERVER['AUTH_JWT_CURRENT_KID'] = 'nonexistent';
        putenv('AUTH_JWT_CURRENT_KID=nonexistent');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage("Current JWT key 'nonexistent' not found in AUTH_JWT_KEYS");
            Token::issue(['sub' => '123']);
        } finally {
            // Restore original values
            if ($originalKid !== null) {
                $_ENV['AUTH_JWT_CURRENT_KID'] = $originalKid;
                putenv("AUTH_JWT_CURRENT_KID=$originalKid");
            } else {
                unset($_ENV['AUTH_JWT_CURRENT_KID']);
                putenv('AUTH_JWT_CURRENT_KID');
            }
            if ($originalServerKid !== null) {
                $_SERVER['AUTH_JWT_CURRENT_KID'] = $originalServerKid;
            } else {
                unset($_SERVER['AUTH_JWT_CURRENT_KID']);
            }
        }
    }

    public function test_invalid_key_format_throws_exception(): void
    {
        // Temporarily set invalid key format
        $originalKeys = $_ENV['AUTH_JWT_KEYS'] ?? null;
        $originalServerKeys = $_SERVER['AUTH_JWT_KEYS'] ?? null;

        $_ENV['AUTH_JWT_KEYS'] = 'invalid-format-no-colon';
        $_SERVER['AUTH_JWT_KEYS'] = 'invalid-format-no-colon';
        putenv('AUTH_JWT_KEYS=invalid-format-no-colon');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Invalid JWT key format: invalid-format-no-colon');
            Token::issue(['sub' => '123']);
        } finally {
            // Restore original values
            if ($originalKeys !== null) {
                $_ENV['AUTH_JWT_KEYS'] = $originalKeys;
                putenv("AUTH_JWT_KEYS=$originalKeys");
            } else {
                unset($_ENV['AUTH_JWT_KEYS']);
                putenv('AUTH_JWT_KEYS');
            }
            if ($originalServerKeys !== null) {
                $_SERVER['AUTH_JWT_KEYS'] = $originalServerKeys;
            } else {
                unset($_SERVER['AUTH_JWT_KEYS']);
            }
        }
    }

    public function test_empty_key_parts_throws_exception(): void
    {
        // Temporarily set empty key parts
        $originalKeys = $_ENV['AUTH_JWT_KEYS'] ?? null;
        $originalServerKeys = $_SERVER['AUTH_JWT_KEYS'] ?? null;

        $_ENV['AUTH_JWT_KEYS'] = 'main:,empty:';
        $_SERVER['AUTH_JWT_KEYS'] = 'main:,empty:';
        putenv('AUTH_JWT_KEYS=main:,empty:');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Empty key ID or secret');
            Token::issue(['sub' => '123']);
        } finally {
            // Restore original values
            if ($originalKeys !== null) {
                $_ENV['AUTH_JWT_KEYS'] = $originalKeys;
                putenv("AUTH_JWT_KEYS=$originalKeys");
            } else {
                unset($_ENV['AUTH_JWT_KEYS']);
                putenv('AUTH_JWT_KEYS');
            }
            if ($originalServerKeys !== null) {
                $_SERVER['AUTH_JWT_KEYS'] = $originalServerKeys;
            } else {
                unset($_SERVER['AUTH_JWT_KEYS']);
            }
        }
    }

    public function test_jti_is_unique(): void
    {
        $token1 = Token::issue(['sub' => '123']);
        $token2 = Token::issue(['sub' => '123']);

        $decoded1 = Token::verify($token1);
        $decoded2 = Token::verify($token2);

        $this->assertNotEquals($decoded1['jti'], $decoded2['jti']);
    }

    public function test_uses_default_ttl_from_env(): void
    {
        // Temporarily set custom TTL
        $originalTtl = $_ENV['AUTH_TOKEN_TTL'] ?? null;
        $originalServerTtl = $_SERVER['AUTH_TOKEN_TTL'] ?? null;

        $_ENV['AUTH_TOKEN_TTL'] = '1800';
        $_SERVER['AUTH_TOKEN_TTL'] = '1800';
        putenv('AUTH_TOKEN_TTL=1800');

        try {
            $token = Token::issue(['sub' => '123']);
            $decoded = Token::verify($token);

            // Verify the TTL was used correctly (check exp vs iat difference)
            $actualTtl = $decoded['exp'] - $decoded['iat'];
            $this->assertEquals(1800, $actualTtl);
        } finally {
            // Restore original values
            if ($originalTtl !== null) {
                $_ENV['AUTH_TOKEN_TTL'] = $originalTtl;
                putenv("AUTH_TOKEN_TTL=$originalTtl");
            } else {
                unset($_ENV['AUTH_TOKEN_TTL']);
                putenv('AUTH_TOKEN_TTL');
            }
            if ($originalServerTtl !== null) {
                $_SERVER['AUTH_TOKEN_TTL'] = $originalServerTtl;
            } else {
                unset($_SERVER['AUTH_TOKEN_TTL']);
            }
        }
    }

    public function test_uses_fallback_ttl_when_env_missing(): void
    {
        // Temporarily unset TTL to test fallback
        $originalTtl = $_ENV['AUTH_TOKEN_TTL'] ?? null;
        $originalServerTtl = $_SERVER['AUTH_TOKEN_TTL'] ?? null;

        unset($_ENV['AUTH_TOKEN_TTL']);
        unset($_SERVER['AUTH_TOKEN_TTL']);
        putenv('AUTH_TOKEN_TTL');

        try {
            $token = Token::issue(['sub' => '123']);
            $decoded = Token::verify($token);

            // Should use default of 900 seconds (check exp vs iat difference)
            $actualTtl = $decoded['exp'] - $decoded['iat'];
            $this->assertEquals(900, $actualTtl);
        } finally {
            // Restore original values
            if ($originalTtl !== null) {
                $_ENV['AUTH_TOKEN_TTL'] = $originalTtl;
                putenv("AUTH_TOKEN_TTL=$originalTtl");
            }
            if ($originalServerTtl !== null) {
                $_SERVER['AUTH_TOKEN_TTL'] = $originalServerTtl;
            }
        }
    }
}
