<?php

namespace Tests\Feature\Settings;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DebugPasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_update_debug(): void
    {
        $this->withoutExceptionHandling();

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from('/settings/password')
            ->put('/settings/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertSessionHasNoErrors()->assertRedirect('/settings/password');
        expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
    }
}
