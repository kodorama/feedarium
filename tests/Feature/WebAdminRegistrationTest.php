<?php

describe('Admin Registration (Web)', function () {
    it('registers the first admin user via the web route', function () {
        \App\Models\User::query()->delete(); // Ensure no users exist

        $response = $this->post('/register-admin', [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', [
            'email' => 'admin@example.com',
            'is_admin' => true,
        ]);
        $this->assertAuthenticated();
    });
});
