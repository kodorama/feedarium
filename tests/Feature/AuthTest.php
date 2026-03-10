<?php

describe('Authentication', function () {
    it('logs in with valid credentials and returns a token', function () {
        $user = \App\Models\User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertSuccessful();
        $response->assertJsonStructure(['token']);
    });

    it('fails login with invalid credentials', function () {
        $user = \App\Models\User::factory()->create([
            'email' => 'test2@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test2@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnauthorized();
    });

    it('logs out and invalidates token', function () {
        $user = \App\Models\User::factory()->create([
            'email' => 'test3@example.com',
            'password' => bcrypt('password123'),
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout');

        $response->assertSuccessful();
        $response->assertJson(['message' => 'Logged out']);
    });
});
