<?php

use App\Models\User;

describe('Appearance', function () {
    it('shares appearance cookie as Inertia prop when cookie is set', function () {
        $user = User::factory()->create();

        // Use withUnencryptedCookie to bypass encryption for test cookies
        $response = $this->actingAs($user)
            ->withUnencryptedCookie('appearance', 'dark')
            ->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('appearance', 'dark'));
    });

    it('defaults appearance to system when no cookie is set', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('appearance', 'system'));
    });
});
