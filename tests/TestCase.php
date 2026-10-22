<?php

namespace Tests;

use Tests\Support\TestDatabaseSafetyGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $app->bind(\App\Support\OutboundHttp\OutboundHostResolver::class, \Tests\Support\FakeOutboundHostResolver::class);
        $app->bind(\App\Support\OutboundHttp\OutboundPeerAddressVerifier::class, \Tests\Support\FakeOutboundPeerAddressVerifier::class);
        $app->bind(\App\Support\OutboundHttp\OutboundHttpHandlerFactory::class, \Tests\Support\FakeOutboundHttpHandlerFactory::class);

        return $app;
    }

    /**
     * Validate the booted database configuration before RefreshDatabase can migrate it.
     *
     * @return array<class-string, bool>
     */
    protected function setUpTraits()
    {
        TestDatabaseSafetyGuard::assertSafe($this->app);

        return parent::setUpTraits();
    }
}
