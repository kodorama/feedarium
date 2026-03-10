<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [];

    /**
     * Skip CSRF verification entirely when running tests.
     */
    protected function inExceptArray($request): bool
    {
        if ($this->app->runningUnitTests()) {
            return true;
        }

        return parent::inExceptArray($request);
    }
}
