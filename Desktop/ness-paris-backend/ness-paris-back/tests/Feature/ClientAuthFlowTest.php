<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ClientAuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_complete_auth_flow(): void
    {
        Notification::fake();

        // 1. Register
        $registerResponse = $this->postJson('/api/client/register', [
            'name' => 'Client Test',
            'email' => 'client@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'company_name' => 'Ness Pro Retail',
            'legal_name' => 'Ness Pro Retail SARL',
            'company_email' => 'contact@nesspro.com',
            'company_phone' => '0102030405',
            'country' => 'FR',
        ]);

        $registerResponse->assertCreated();

        $user = User::where('email', 'client@example.com')->firstOrFail();

        // 2. Login initial
        $loginResponse = $this->postJson('/api/client/login', [
            'email' => 'client@example.com',
            'password' => 'Password123',
        ]);

        $loginResponse->assertOk()
            ->assertJsonStructure(['token']);

        // 3. Forgot password
        $forgotResponse = $this->postJson('/api/client/forgot-password', [
            'email' => 'client@example.com',
        ]);

        $forgotResponse->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);

        // 4. Reset password
        $token = Password::broker()->createToken($user);

        $resetResponse = $this->postJson('/api/client/reset-password', [
            'email' => 'client@example.com',
            'token' => $token,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $resetResponse->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123', $user->password));

        // 5. Login with new password
        $newLoginResponse = $this->postJson('/api/client/login', [
            'email' => 'client@example.com',
            'password' => 'NewPassword123',
        ]);

        $newLoginResponse->assertOk()
            ->assertJsonStructure(['token']);
    }
}