<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRegisterThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_is_rate_limited_after_too_many_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/client/register', [
                'name' => 'Client '.$i,
                'email' => "client{$i}@example.com",
                'password' => 'Password123',
                'password_confirmation' => 'Password123',
                'company_name' => 'Ness Pro Retail '.$i,
                'country' => 'FR',
            ]);
        }

        $response = $this->postJson('/api/client/register', [
            'name' => 'Client 99',
            'email' => 'client99@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'company_name' => 'Ness Pro Retail 99',
            'country' => 'FR',
        ]);

        $response->assertTooManyRequests();
    }
}