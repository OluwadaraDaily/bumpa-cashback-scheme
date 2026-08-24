<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->ensureSafeTestingEnvironment();

        parent::setUp();

        $this->ensureSafeApplicationConfiguration();
    }

    private function ensureSafeTestingEnvironment(): void
    {
        $expectedEnvironment = [
            'APP_ENV' => 'testing',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'DB_URL' => '',
            'PAYMENT_PROVIDER' => 'fake',
            'QUEUE_CONNECTION' => 'sync',
        ];

        foreach ($expectedEnvironment as $name => $expectedValue) {
            $environmentStores = [
                'getenv()' => getenv($name),
                '$_ENV' => $_ENV[$name] ?? null,
                '$_SERVER' => $_SERVER[$name] ?? null,
            ];

            foreach ($environmentStores as $store => $actualValue) {
                if ($actualValue !== $expectedValue) {
                    throw new \RuntimeException(
                        "Unsafe test environment: {$name} in {$store} must be {$expectedValue}.",
                    );
                }
            }
        }
    }

    private function ensureSafeApplicationConfiguration(): void
    {
        $expectedConfiguration = [
            'app.env' => 'testing',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'services.payment.provider' => 'fake',
        ];

        foreach ($expectedConfiguration as $name => $expectedValue) {
            $actualValue = config($name);

            if ($actualValue !== $expectedValue) {
                throw new \RuntimeException(
                    "Unsafe test configuration: {$name} must be {$expectedValue}.",
                );
            }
        }
    }
}
