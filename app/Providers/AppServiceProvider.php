<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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
        // Force HTTPS and use APP_URL for all generated URLs
        $appUrl = config('app.url');
        if ($appUrl && $appUrl !== 'http://localhost') {
            URL::forceScheme('https');
            URL::forceRootUrl($appUrl);
            
            // Also set the URL for the request to ensure Inertia uses correct domain
            $this->app['request']->server->set('HTTP_HOST', parse_url($appUrl, PHP_URL_HOST));
            $this->app['request']->server->set('HTTPS', 'on');
        }
    }
}
