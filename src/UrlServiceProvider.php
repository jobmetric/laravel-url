<?php

namespace JobMetric\Url;

use Illuminate\Support\Facades\Route;
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
}
