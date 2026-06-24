<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_rate_limited_after_too_many_attempts(): void
    {
        User::factory()->create([
            'email' => 'client@example.com',
            'password' => Hash::make('Password123'),
            'role' => 'client',
            'is_admin' => false,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/client/login', [
                'email' => 'client@example.com',
                'password' => 'WrongPassword123',
            ]);
        }

        $response = $this->postJson('/api/client/login', [
            'email' => 'client@example.com',
            'password' => 'WrongPassword123',
        ]);

        $response->assertTooManyRequests();
    }
}