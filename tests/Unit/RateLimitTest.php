<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use LeanPHP\RateLimit\FileStore;
use LeanPHP\RateLimit\ApcuStore;

class RateLimitTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/leanphp_ratelimit_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testFileStoreBasicFunctionality(): void
    {
        $store = new FileStore($this->tempDir);
        $key = 'test-key';
        $limit = 5;
        $window = 60;

        // First hit should work
        $result = $store->hit($key, $limit, $window);
        $this->assertEquals(4, $result['remaining']);
        $this->assertIsInt($result['resetAt']);
        $this->assertGreaterThan(time(), $result['resetAt']);

        // Should be allowed since we're under the limit
        $this->assertTrue($store->allow($key, $limit, $window));

        // Retry after should be 0 since we're allowed
        $this->assertEquals(0, $store->retryAfter($key, $limit, $window));
    }

    public function testFileStoreRateLimitExceeded(): void
    {
        $store = new FileStore($this->tempDir);
        $key = 'test-key-exceeded';
        $limit = 2;
        $window = 60;

        // Hit the limit
        $store->hit($key, $limit, $window);
        $result = $store->hit($key, $limit, $window);

        $this->assertEquals(0, $result['remaining']);

        // Should not be allowed anymore
        $this->assertFalse($store->allow($key, $limit, $window));

        // Should have a retry after time
        $this->assertGreaterThan(0, $store->retryAfter($key, $limit, $window));
    }

    public function testFileStoreWindowExpiry(): void
    {
        $store = new FileStore($this->tempDir);
        $key = 'test-key-expiry';
        $limit = 2;
        $window = 1; // 1 second window

        // Hit the limit
        $store->hit($key, $limit, $window);
        $store->hit($key, $limit, $window);

        // Should be at the limit
        $this->assertFalse($store->allow($key, $limit, $window));

        // Wait for window to expire
        sleep(2);

        // Should be allowed again
        $this->assertTrue($store->allow($key, $limit, $window));
    }

    public function testFileStoreConcurrentAccess(): void
    {
        $store = new FileStore($this->tempDir);
        $key = 'test-key-concurrent';
        $limit = 10;
        $window = 60;

        // Simulate multiple concurrent hits
        for ($i = 0; $i < 5; $i++) {
            $result = $store->hit($key, $limit, $window);
            $this->assertArrayHasKey('remaining', $result);
            $this->assertArrayHasKey('resetAt', $result);
            $this->assertEquals(10 - ($i + 1), $result['remaining']);
        }
    }

    public function testApcuStoreWhenExtensionLoaded(): void
    {
        if (!extension_loaded('apcu')) {
            $this->markTestSkipped('APCu extension not loaded');
        }

        $store = new ApcuStore();
        $key = 'test-apcu-key';
        $limit = 5;
        $window = 60;

        // First hit should work
        $result = $store->hit($key, $limit, $window);
        $this->assertEquals(4, $result['remaining']);
        $this->assertIsInt($result['resetAt']);
        $this->assertGreaterThan(time(), $result['resetAt']);

        // Should be allowed since we're under the limit
        $this->assertTrue($store->allow($key, $limit, $window));

        // Retry after should be 0 since we're allowed
        $this->assertEquals(0, $store->retryAfter($key, $limit, $window));
    }

    public function testApcuStoreThrowsExceptionWhenExtensionNotLoaded(): void
    {
        if (extension_loaded('apcu')) {
            $this->markTestSkipped('APCu extension is loaded, cannot test exception');
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APCu extension is not loaded');

        $store = new ApcuStore();
        $store->hit('test', 5, 60);
    }

    public function testFileStoreCreatesDirectory(): void
    {
        $newDir = $this->tempDir . '/nested/path';
        $this->assertDirectoryDoesNotExist($newDir);

        new FileStore($newDir);

        $this->assertDirectoryExists($newDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}

