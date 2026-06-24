<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ClientForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_request_password_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'client@example.com',
            'role' => 'client',
            'is_admin' => false,
        ]);

        $response = $this->postJson('/api/client/forgot-password', [
            'email' => 'client@example.com',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_requires_valid_email(): void
    {
        $response = $this->postJson('/api/client/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}