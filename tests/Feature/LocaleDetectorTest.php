<?php

use App\Models\Setting;
use App\Domains\Settings\Support\LocaleDetector;

it('detects at least en and tr as available locales', function () {
    $available = LocaleDetector::available();

    expect($available->contains('en'))->toBeTrue()
        ->and($available->contains('tr'))->toBeTrue();
});

it('returns metadata for available locales', function () {
    $withMeta = LocaleDetector::availableWithMetadata();

    $en = $withMeta->firstWhere('code', 'en');
    $tr = $withMeta->firstWhere('code', 'tr');

    expect($en)->not->toBeNull()
        ->and($en['name'])->toBe('English')
        ->and($en['rtl'])->toBeFalse()
        ->and($tr)->not->toBeNull()
        ->and($tr['native'])->toBe('Türkçe')
        ->and($tr['rtl'])->toBeFalse();
});

it('resolves to stored locale when valid', function () {
    expect(LocaleDetector::resolve('tr'))->toBe('tr');
});

it('falls back to en when stored locale is invalid', function () {
    expect(LocaleDetector::resolve('xx'))->toBe('en');
});

it('resolves to en when no locale is stored', function () {
    expect(LocaleDetector::resolve(null))->toBe('en');
});

it('saves and returns locale via settings api', function () {
    $admin = \App\Models\User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->patchJson('/api/settings', ['locale' => 'tr'])
        ->assertOk();

    expect(Setting::get('locale'))->toBe('tr');

    $response = $this->actingAs($admin)->getJson('/api/settings');
    $response->assertOk()->assertJsonPath('locale', 'tr');
});

it('rejects invalid locale via settings api', function () {
    $admin = \App\Models\User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->patchJson('/api/settings', ['locale' => 'xx'])
        ->assertUnprocessable();
});

it('shares locale and availableLocales via inertia middleware', function () {
    /** @var \App\Http\Middleware\HandleInertiaRequests $middleware */
    $middleware = app(\App\Http\Middleware\HandleInertiaRequests::class);

    $request = \Illuminate\Http\Request::create('/dashboard');
    $user = \App\Models\User::factory()->create();
    $request->setUserResolver(fn () => $user);

    $shared = $middleware->share($request);

    expect($shared)->toHaveKey('locale')
        ->and($shared)->toHaveKey('availableLocales')
        ->and($shared['locale'])->toBe('en')
        ->and($shared['availableLocales'])->toBeArray()
        ->and(collect($shared['availableLocales'])->pluck('code')->toArray())->toContain('en', 'tr');
});
