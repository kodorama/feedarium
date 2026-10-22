<?php

namespace Tests;

use Tests\Support\TestDatabaseSafetyGuard;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
