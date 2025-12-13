<?php

namespace JobMetric\Url;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Route;
use JobMetric\EventSystem\Support\EventRegistry;
use JobMetric\PackageCore\Exceptions\MigrationFolderNotFoundException;
use JobMetric\PackageCore\PackageCore;
use JobMetric\PackageCore\PackageCoreServiceProvider;
use JobMetric\Url\Http\Controllers\FullUrlController;

class UrlServiceProvider extends PackageCoreServiceProvider
{
    /**
     * @throws MigrationFolderNotFoundException
     */
    public function configuration(PackageCore $package): void
    {
        $package->name('laravel-url')
            ->hasConfig()
            ->hasTranslation()
            ->hasMigration();
    }

    /**
     * before boot package
     *
     * @return void
     */
    public function beforeBootPackage(): void
    {
        if (config('url.register_fallback', true)) {
            Route::middleware(config('url.fallback_middleware', ['web']))->group(function () {
                Route::fallback([FullUrlController::class, '__invoke'])->name('JobMetric.url.fallback');
            });
        }
    }

    /**
     * after boot package
     *
     * @return void
     * @throws BindingResolutionException
     */
    public function afterBootPackage(): void
    {
        // Register events if EventRegistry is available
        // This ensures EventRegistry is available if EventSystemServiceProvider is loaded
        if ($this->app->bound('EventRegistry')) {
            /** @var EventRegistry $registry */
            $registry = $this->app->make('EventRegistry');

            // URL Events
            $registry->register(\JobMetric\Url\Events\UrlChanged::class);
        }
    }
}
