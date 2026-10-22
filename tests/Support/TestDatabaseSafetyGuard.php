<?php

namespace Tests\Support;

use LogicException;
use Illuminate\Contracts\Foundation\Application;

/** Prevent destructive test traits from running against persistent databases. */
final class TestDatabaseSafetyGuard
{
    public static function assertSafe(Application $app): void
    {
        $config = $app->make('config');
        $sqlite = $config->get('database.connections.sqlite', []);
        $sqliteUrl = $sqlite['url'] ?? null;

        $isSafe = $app->environment() === 'testing'
            && $config->get('database.default') === 'sqlite'
            && ($sqlite['driver'] ?? null) === 'sqlite'
            && ($sqlite['database'] ?? null) === ':memory:'
            && ($sqliteUrl === null || $sqliteUrl === '');

        if (! $isSafe) {
            throw new LogicException(
                'Refusing to run tests: effective configuration must use APP_ENV=testing and the sqlite :memory: database with no connection URL.'
            );
        }
    }
}
