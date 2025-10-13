<?php

describe('User CRUD', function () {
    it('can create a user', function () {
        $response = $this->postJson('/api/users', [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'password123',
        ]);
        $response->assertSuccessful();
        $this->assertDatabaseHas('users', [
            'email' => 'testuser@example.com',
        ]);
    });

    it('can list users', function () {
        $user = \App\Models\User::factory()->create();
        $response = $this->getJson('/api/users');
        $response->assertSuccessful();
        $response->assertJsonFragment(['email' => $user->email]);
    });

    it('can update a user', function () {
        $user = \App\Models\User::factory()->create();
        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'Updated Name',
            'email' => $user->email,
        ]);
        $response->assertSuccessful();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    });

    it('can delete a user', function () {
        $user = \App\Models\User::factory()->create();
        $response = $this->deleteJson("/api/users/{$user->id}");
        $response->assertNoContent();
        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    });
});
