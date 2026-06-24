<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ClientResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'client@example.com',
            'password' => Hash::make('OldPassword123'),
            'role' => 'client',
            'is_admin' => false,
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/client/reset-password', [
            'email' => 'client@example.com',
            'token' => $token,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message']);

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123', $user->password));
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'client@example.com',
            'password' => Hash::make('OldPassword123'),
            'role' => 'client',
            'is_admin' => false,
        ]);

        $response = $this->postJson('/api/client/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message']);
    }
}