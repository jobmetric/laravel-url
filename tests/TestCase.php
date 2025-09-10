<?php

namespace JobMetric\Url\Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JobMetric\Url\UrlServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Random\RandomException;

class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Register package service providers.
     *
     * @param Application $app
     * @return array<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            UrlServiceProvider::class,
        ];
    }

    /**
     * Configure the application for tests.
     *
     * - Set an APP_KEY to satisfy EncryptCookies / encrypter.
     * - Use in-memory drivers for database/session/cache.
     * - Ensure our fallback route is enabled with the desired middleware.
     *
     * @param Application $app
     * @return void
     * @throws RandomException
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Database (sqlite in-memory)
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // App key & cipher (fixes MissingAppKeyException)
        // Use a random base64-encoded 32-byte key just for tests.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $app['config']->set('app.cipher', 'AES-256-CBC');

        // Session / Cache to in-memory drivers for test isolation
        $app['config']->set('session.driver', 'array');
        $app['config']->set('cache.default', 'array');

        // Make sure the package fallback is active and uses the web middleware
        $app['config']->set('url.register_fallback', true);
        $app['config']->set('url.fallback_middleware', ['web']);
    }

    /**
     * Bootstrap test environment (load stub migrations).
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Load the package’s stub migrations (categories/products)
        loadMigrationPath(__DIR__ . '/database/migrations');
    }
}
