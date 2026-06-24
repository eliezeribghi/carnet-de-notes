<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientPortalSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function makeApprovedCompany(array $overrides = []): Company
    {
        return Company::factory()->create(array_merge([
            'status' => 'approved',
            'is_active' => true,
        ], $overrides));
    }

    protected function makePendingCompany(array $overrides = []): Company
    {
        return Company::factory()->create(array_merge([
            'status' => 'pending_review',
            'is_active' => true,
        ], $overrides));
    }

    protected function makeClient(?Company $company = null, array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'company_id' => $company?->id,
            'role' => 'client',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));
    }

    protected function makeAdmin(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'is_admin' => true,
            'role' => 'admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ], $overrides));
    }

    public function test_guest_cannot_access_client_me_route(): void
    {
        $response = $this->getJson('/api/client/me');

        $response->assertStatus(401);
    }

    public function test_admin_cannot_access_client_portal_route(): void
    {
        $admin = $this->makeAdmin();

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/client/me');

        $response->assertStatus(403);
    }

    public function test_inactive_client_cannot_access_portal(): void
    {
        $company = $this->makeApprovedCompany();
        $client = $this->makeClient($company, [
            'is_active' => false,
        ]);

        Sanctum::actingAs($client);

        $response = $this->getJson('/api/client/me');

        $response->assertStatus(403);
    }

    public function test_client_with_pending_company_cannot_access_portal(): void
    {
        $company = $this->makePendingCompany();
        $client = $this->makeClient($company);

        Sanctum::actingAs($client);

        $response = $this->getJson('/api/client/me');

        $response->assertStatus(403);
    }

    public function test_approved_active_verified_client_can_access_me_route(): void
    {
        $company = $this->makeApprovedCompany([
            'name' => 'Ness Pro Retail',
        ]);

        $client = $this->makeClient($company, [
            'name' => 'Client Test',
            'email' => 'client@example.com',
        ]);

        Sanctum::actingAs($client);

        $response = $this->getJson('/api/client/me');

        $response->assertOk()
            ->assertJsonFragment([
                'email' => 'client@example.com',
                'name' => 'Client Test',
            ]);
    }
}
