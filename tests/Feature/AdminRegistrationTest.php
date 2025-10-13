<?php

describe('Admin Registration', function () {
    it('redirects to admin registration if no users exist', function () {
        \App\Models\User::query()->delete();
        $response = $this->get('/');
        $response->assertInertia(fn ($page) => $page->component('auth/RegisterAdmin'));
    });

    it('does not redirect to admin registration if users exist', function () {
        \App\Models\User::factory()->create(['is_admin' => true]);
        $response = $this->get('/');
        $response->assertInertia(fn ($page) => $page->component('Welcome'));
    });

    it('registers an admin user via API', function () {
        \App\Models\User::query()->delete();
        $data = [
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
        $response = $this->postJson('/api/register-admin', $data);
        $response->assertSuccessful();
        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'is_admin' => true,
        ]);
    });
});
