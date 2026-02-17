<?php

namespace App\Providers;

use App\Models\LandingPageSetting;
use App\Models\CmsHeroImage;
use App\Models\CmsLeader;
use App\Models\CmsPartner;
use App\Models\CmsLoginBackground;
use App\Observers\CmsObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register CMS observer for auto cache clearing
        LandingPageSetting::observe(CmsObserver::class);
        CmsHeroImage::observe(CmsObserver::class);
        CmsLeader::observe(CmsObserver::class);
        CmsPartner::observe(CmsObserver::class);
        CmsLoginBackground::observe(CmsObserver::class);
    }
}
