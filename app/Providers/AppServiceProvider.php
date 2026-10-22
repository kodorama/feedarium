<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Support\OutboundHttp\OutboundHostResolver;
use App\Support\OutboundHttp\OutboundHttpHandlerFactory;
use App\Support\OutboundHttp\SystemOutboundHostResolver;
use App\Support\OutboundHttp\OutboundPeerAddressVerifier;
use App\Support\OutboundHttp\CurlOutboundHttpHandlerFactory;
use App\Support\OutboundHttp\SystemOutboundPeerAddressVerifier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OutboundHostResolver::class, SystemOutboundHostResolver::class);
        $this->app->singleton(OutboundPeerAddressVerifier::class, SystemOutboundPeerAddressVerifier::class);
        $this->app->singleton(OutboundHttpHandlerFactory::class, CurlOutboundHttpHandlerFactory::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {}
}
