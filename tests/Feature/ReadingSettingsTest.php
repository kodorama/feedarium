<?php

use App\Models\User;
use App\Models\Setting;

describe('Reading settings page', function () {
    it('renders the reading settings page for authenticated users', function () {
        $user = User::factory()->create();

        $this->withoutVite()
            ->actingAs($user)
            ->get('/settings/reading')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('settings/Reading'));
    });

    it('redirects unauthenticated users away from reading settings', function () {
        $this->get('/settings/reading')->assertRedirect('/login');
    });
});

describe('GET /api/settings', function () {
    it('returns default retention settings for admin', function () {
        Setting::set('news_retention_enabled', 'true');
        Setting::set('news_retention_days', '90');

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->getJson('/api/settings')
            ->assertOk()
            ->assertJson([
                'news_retention_enabled' => true,
                'news_retention_days' => 90,
            ]);
    });

    it('requires authentication', function () {
        $this->getJson('/api/settings')->assertUnauthorized();
    });
});

describe('PATCH /api/settings', function () {
    it('allows admin to update retention settings', function () {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patchJson('/api/settings', [
                'news_retention_enabled' => false,
                'news_retention_days' => 60,
            ])
            ->assertOk()
            ->assertJson(['message' => 'Settings updated successfully.']);

        expect(Setting::get('news_retention_enabled'))->toBe('false')
            ->and(Setting::get('news_retention_days'))->toBe('60');
    });

    it('rejects non-admin users', function () {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->patchJson('/api/settings', [
                'news_retention_enabled' => true,
                'news_retention_days' => 30,
            ])
            ->assertForbidden();
    });

    it('validates retention days are within bounds', function () {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patchJson('/api/settings', [
                'news_retention_enabled' => true,
                'news_retention_days' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['news_retention_days']);
    });
});
