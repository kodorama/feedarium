<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('profile page is displayed', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/settings/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'locale' => 'en',
        'timezone' => 'UTC',
    ])->assertSessionHasNoErrors()->assertRedirect('/settings/profile');

    $user->refresh();
    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->locale)->toBe('en');
    expect($user->timezone)->toBe('UTC');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => 'Test User',
        'email' => $user->email,
        'locale' => 'en',
        'timezone' => 'UTC',
    ])->assertSessionHasNoErrors()->assertRedirect('/settings/profile');

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->delete('/settings/profile', [
        'password' => 'password',
    ])->assertSessionHasNoErrors()->assertRedirect('/');

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from('/settings/profile')->delete('/settings/profile', [
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('password')->assertRedirect('/settings/profile');

    expect($user->fresh())->not->toBeNull();
});

test('locale is validated against available locales', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => 'Test User',
        'email' => $user->email,
        'locale' => 'xx_INVALID',
        'timezone' => 'UTC',
    ])->assertSessionHasErrors('locale');
});

test('timezone is validated against php timezone identifiers', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => 'Test User',
        'email' => $user->email,
        'locale' => 'en',
        'timezone' => 'Not/ATimezone',
    ])->assertSessionHasErrors('timezone');
});

test('user locale and timezone are persisted', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/settings/profile', [
        'name' => 'Test User',
        'email' => $user->email,
        'locale' => 'tr',
        'timezone' => 'Europe/Istanbul',
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->locale)->toBe('tr')
        ->and($user->timezone)->toBe('Europe/Istanbul');
});
