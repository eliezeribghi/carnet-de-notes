<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_register_with_company(): void
    {
        $this->withoutExceptionHandling();
        $payload = [
            'name' => 'Client Test',
            'email' => 'client@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'company_name' => 'Ness Pro Retail',
            'legal_name' => 'Ness Pro Retail SARL',
            'company_email' => 'contact@nesspro.com',
            'company_phone' => '0102030405',
            'country' => 'FR',
        ];

        $response = $this->postJson('/api/client/register', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'message' => 'Demande de compte client créée avec succès.',
            ])
            ->assertJsonPath('user.email', 'client@example.com')
            ->assertJsonPath('company.name', 'Ness Pro Retail')
            ->assertJsonPath('company.status', 'pending_review');

        $this->assertDatabaseHas('users', [
            'email' => 'client@example.com',
            'role' => 'client',
            'is_admin' => false,
        ]);

        $this->assertDatabaseHas('companies', [
            'name' => 'Ness Pro Retail',
            'status' => 'pending_review',
            'is_active' => true,
        ]);
    }

    public function test_register_requires_valid_data(): void
    {
        $response = $this->postJson('/api/client/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'email',
                'password',
                'company_name',
            ]);
    }
}