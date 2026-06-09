<?php

namespace Lenius\LaravelAltid\Tests;

use Lenius\LaravelAltid\LaravelAltidServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelAltidServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('cache.default', 'array');
        config()->set('app.url', 'https://altid-test.example.test');
        config()->set('altid.age_claim', 'age_over_18');
        config()->set('altid.transaction_ttl_minutes', 15);
        config()->set('altid.accept_unverified_responses', true);
        config()->set('altid.debug', false);
    }
}
