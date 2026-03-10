<?php

describe('List Users', function () {
    it('returns all users for authenticated requests', function () {
        $user = \App\Models\User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $users = \App\Models\User::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users');

        $response->assertSuccessful();
        $response->assertJsonStructure(['users']);
        expect(count($response->json('users')))->toBeGreaterThanOrEqual(1);
    });

    it('returns forbidden for unauthenticated requests', function () {
        $response = $this->getJson('/api/users');
        $response->assertUnauthorized();
    });
});
