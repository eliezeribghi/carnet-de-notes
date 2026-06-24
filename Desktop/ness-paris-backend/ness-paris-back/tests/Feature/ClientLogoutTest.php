<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_client_can_logout(): void
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
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('client-portal');
        $plainTextToken = $token->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $plainTextToken)
            ->postJson('/api/client/logout');

        $response->assertOk()
            ->assertJsonFragment([
                'message' => 'Déconnexion réussie.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_guest_cannot_logout(): void
    {
        $response = $this->postJson('/api/client/logout');

        $response->assertStatus(401);
    }
}