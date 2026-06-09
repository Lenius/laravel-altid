<?php

namespace Lenius\LaravelAltid;

use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelAltidServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-altid')
            ->hasConfigFile('altid');
    }

    public function packageRegistered(): void
    {
        $this->app->bind(AltIdAgePresentationValidator::class, MdocAltIdAgePresentationValidator::class);
        $this->app->singleton(AltIdAgeVerificationService::class);
    }

    public function packageBooted(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-altid');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/laravel-altid'),
        ], 'laravel-altid-views');

        $this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/laravel-altid'),
        ], 'laravel-altid-assets');

        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../routes/api.php');

        if (config('altid.register_web_routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }
    }
}
