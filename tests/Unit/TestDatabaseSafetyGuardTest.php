<?php

use Illuminate\Config\Repository;
use Tests\TestCase as BaseTestCase;
use Illuminate\Foundation\Application;
use Tests\Support\TestDatabaseSafetyGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;

final class TestDatabaseSafetyGuardLifecycleHarness extends BaseTestCase
{
    public static int $refreshDatabaseCalls = 0;

    public function configure(Application $app): void
    {
        $this->app = $app;
        $this->traitsUsedByTest = [RefreshDatabase::class => true];
    }

    public function runLifecycleTraits(): array
    {
        return $this->setUpTraits();
    }

    public function refreshDatabase()
    {
        self::$refreshDatabaseCalls++;
    }
}

$makeApplication = static function (array $overrides = []): Application {
    $app = new Application;
    $app->instance('env', $overrides['environment'] ?? 'testing');
    $app->instance('config', new Repository([
        'database' => [
            'default' => $overrides['default'] ?? 'sqlite',
            'connections' => [
                'sqlite' => [
                    'driver' => $overrides['driver'] ?? 'sqlite',
                    'database' => $overrides['database'] ?? ':memory:',
                    'url' => $overrides['url'] ?? null,
                ],
            ],
        ],
    ]));

    return $app;
};

it('accepts only the isolated sqlite in-memory test database', function () use ($makeApplication): void {
    $app = $makeApplication();
    TestDatabaseSafetyGuard::assertSafe($app);

    expect($app->environment())->toBe('testing');
});

it('rejects unsafe effective test database configuration before migrations', function (array $overrides) use ($makeApplication): void {
    expect(fn () => TestDatabaseSafetyGuard::assertSafe($makeApplication($overrides)))
        ->toThrow(LogicException::class, 'Refusing to run tests');
})->with([
    'non-testing application environment' => [['environment' => 'local']],
    'persistent default connection' => [['default' => 'pgsql']],
    'non-sqlite driver' => [['driver' => 'pgsql']],
    'file-backed sqlite database' => [['database' => '/var/lib/app.sqlite']],
    'sqlite connection URL' => [['url' => 'sqlite:///var/lib/app.sqlite']],
]);

it('rejects synthetic stale cached postgres configuration before the refresh database trait', function () use ($makeApplication): void {
    $harness = (new ReflectionClass(TestDatabaseSafetyGuardLifecycleHarness::class))->newInstanceWithoutConstructor();
    $harness->configure($makeApplication([
        'default' => 'pgsql',
        'driver' => 'pgsql',
        'database' => 'feedarium',
        'url' => 'pgsql://persistent.example/feedarium',
    ]));
    TestDatabaseSafetyGuardLifecycleHarness::$refreshDatabaseCalls = 0;

    expect(fn () => $harness->runLifecycleTraits())
        ->toThrow(LogicException::class, 'Refusing to run tests');
    expect(TestDatabaseSafetyGuardLifecycleHarness::$refreshDatabaseCalls)->toBe(0);
});

it('allows the refresh database trait hook only after isolated config passes', function () use ($makeApplication): void {
    $harness = (new ReflectionClass(TestDatabaseSafetyGuardLifecycleHarness::class))->newInstanceWithoutConstructor();
    $harness->configure($makeApplication());
    TestDatabaseSafetyGuardLifecycleHarness::$refreshDatabaseCalls = 0;

    $traits = $harness->runLifecycleTraits();

    expect($traits[RefreshDatabase::class])->toBeTrue();
    expect(TestDatabaseSafetyGuardLifecycleHarness::$refreshDatabaseCalls)->toBe(1);
});
