<?php

namespace JobMetric\Url;

use Illuminate\Support\Facades\Blade;
use JobMetric\PackageCore\Exceptions\MigrationFolderNotFoundException;
use JobMetric\PackageCore\Exceptions\ViewFolderNotFoundException;
use JobMetric\PackageCore\PackageCore;
use JobMetric\PackageCore\PackageCoreServiceProvider;
use JobMetric\Url\View\Components\UrlSlug;

class UrlServiceProvider extends PackageCoreServiceProvider
{
    /**
     * @throws MigrationFolderNotFoundException
     * @throws ViewFolderNotFoundException
     */
    public function configuration(PackageCore $package): void
    {
        $package->name('laravel-url')
            ->hasConfig()
            ->hasView()
            ->hasTranslation()
            ->hasMigration();
    }

    /**
     * After Boot Package
     *
     * @return void
     */
    public function afterBootPackage(): void
    {
        // add alias for components
        Blade::component(UrlSlug::class, 'url-slug');
    }
}
