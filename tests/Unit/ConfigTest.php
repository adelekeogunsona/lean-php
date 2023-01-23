<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private array $originalEnv = [];

    protected function setUp(): void
    {
        // Store original environment variables from both $_ENV and $_SERVER
        $this->originalEnv = [
            'APP_ENV' => ['env' => $_ENV['APP_ENV'] ?? null, 'server' => $_SERVER['APP_ENV'] ?? null],
            'APP_DEBUG' => ['env' => $_ENV['APP_DEBUG'] ?? null, 'server' => $_SERVER['APP_DEBUG'] ?? null],
            'APP_URL' => ['env' => $_ENV['APP_URL'] ?? null, 'server' => $_SERVER['APP_URL'] ?? null],
            'APP_TIMEZONE' => ['env' => $_ENV['APP_TIMEZONE'] ?? null, 'server' => $_SERVER['APP_TIMEZONE'] ?? null],
            'LOG_PATH' => ['env' => $_ENV['LOG_PATH'] ?? null, 'server' => $_SERVER['LOG_PATH'] ?? null],
        ];
    }

    protected function tearDown(): void
    {
        // Restore original environment variables to both $_ENV and $_SERVER
        foreach ($this->originalEnv as $key => $values) {
            if ($values['env'] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $values['env'];
            }

            if ($values['server'] === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $values['server'];
            }
        }
    }

    public function test_config_loads_environment_values(): void
    {
        // Set some test environment values
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['APP_DEBUG'] = 'false';
        $_ENV['APP_URL'] = 'https://api.test.com';
        $_ENV['APP_TIMEZONE'] = 'America/New_York';
        $_ENV['LOG_PATH'] = 'storage/logs/test.log';

        $config = require __DIR__ . '/../../config/app.php';

        $this->assertIsArray($config);
        $this->assertEquals('testing', $config['env']);
        $this->assertFalse($config['debug']);
        $this->assertEquals('https://api.test.com', $config['url']);
        $this->assertEquals('America/New_York', $config['timezone']);
        $this->assertEquals('storage/logs/test.log', $config['log_path']);
    }

    public function test_config_uses_defaults_when_env_not_set(): void
    {
        // Test env functions with non-existent variables to verify defaults work
        require_once __DIR__ . '/../../src/Support/env.php';

        $this->assertEquals('development', env_string('NON_EXISTENT_ENV', 'development'));
        $this->assertTrue(env_bool('NON_EXISTENT_BOOL', true));
        $this->assertEquals('http://localhost:8000', env_string('NON_EXISTENT_URL', 'http://localhost:8000'));
        $this->assertEquals('UTC', env_string('NON_EXISTENT_TIMEZONE', 'UTC'));
        $this->assertEquals('storage/logs/app.log', env_string('NON_EXISTENT_LOG', 'storage/logs/app.log'));
    }
}

class EnvHelperTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear any existing env vars for clean tests
        unset($_ENV['TEST_STRING'], $_ENV['TEST_BOOL'], $_ENV['TEST_INT']);
    }

    public function test_env_string_returns_string_value(): void
    {
        $_ENV['TEST_STRING'] = 'hello world';

        require_once __DIR__ . '/../../src/Support/env.php';

        $this->assertEquals('hello world', env_string('TEST_STRING'));
    }

    public function test_env_string_returns_default_when_not_set(): void
    {
        require_once __DIR__ . '/../../src/Support/env.php';

        $this->assertEquals('default', env_string('NON_EXISTENT', 'default'));
        $this->assertNull(env_string('NON_EXISTENT'));
    }

    public function test_env_bool_parses_boolean_values(): void
    {
        require_once __DIR__ . '/../../src/Support/env.php';

        $_ENV['TEST_BOOL'] = 'true';
        $this->assertTrue(env_bool('TEST_BOOL'));

        $_ENV['TEST_BOOL'] = '1';
        $this->assertTrue(env_bool('TEST_BOOL'));

        $_ENV['TEST_BOOL'] = 'yes';
        $this->assertTrue(env_bool('TEST_BOOL'));

        $_ENV['TEST_BOOL'] = 'false';
        $this->assertFalse(env_bool('TEST_BOOL'));

        $_ENV['TEST_BOOL'] = '0';
        $this->assertFalse(env_bool('TEST_BOOL'));

        $_ENV['TEST_BOOL'] = '';
        $this->assertFalse(env_bool('TEST_BOOL'));
    }

    public function test_env_bool_returns_default_for_invalid_values(): void
    {
        require_once __DIR__ . '/../../src/Support/env.php';

        $_ENV['TEST_BOOL'] = 'invalid';
        $this->assertTrue(env_bool('TEST_BOOL', true));
        $this->assertNull(env_bool('NON_EXISTENT'));
    }

    public function test_env_int_parses_integer_values(): void
    {
        require_once __DIR__ . '/../../src/Support/env.php';

        $_ENV['TEST_INT'] = '123';
        $this->assertEquals(123, env_int('TEST_INT'));

        $_ENV['TEST_INT'] = '-456';
        $this->assertEquals(-456, env_int('TEST_INT'));
    }

    public function test_env_int_returns_default_for_invalid_values(): void
    {
        require_once __DIR__ . '/../../src/Support/env.php';

        $_ENV['TEST_INT'] = 'not-a-number';
        $this->assertEquals(999, env_int('TEST_INT', 999));

        $_ENV['TEST_INT'] = '';
        $this->assertEquals(888, env_int('TEST_INT', 888));

        $this->assertNull(env_int('NON_EXISTENT'));
    }
}
