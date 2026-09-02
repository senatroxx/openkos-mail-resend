<?php

namespace Tests;

use OpenKOS\Core\Contracts\SettingsStore;
use OpenKOS\Platform\PlatformServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app->bind(SettingsStore::class, fn () => new class implements SettingsStore
        {
            public function get(string $key): mixed
            {
                return null;
            }

            public function set(string $key, mixed $value, string $type): void {}
        });
    }

    protected function getPackageProviders($app): array
    {
        return [
            PlatformServiceProvider::class,
        ];
    }
}
