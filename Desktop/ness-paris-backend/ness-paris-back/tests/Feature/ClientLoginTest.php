<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_login_with_valid_credentials(): void
    {
        $company = Company::factory()->create([
            'status' => 'approved',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'client',
            'is_admin' => false,
            'is_active' => true,
            'email' => 'client@example.com',
            'password' => Hash::make('Password123'),
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/client/login', [
            'email' => 'client@example.com',
            'password' => 'Password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.email', 'client@example.com')
            ->assertJsonStructure([
                'message',
                'token',
                'token_type',
                'user',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/client/login', [
            'email' => 'client@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Identifiants invalides.',
            ]);
    }
}