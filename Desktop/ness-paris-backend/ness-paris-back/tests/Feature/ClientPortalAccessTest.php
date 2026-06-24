<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientPortalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_client_cannot_access_portal(): void
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
            'email_verified_at' => null,
        ]);

        $token = $user->createToken('client-portal')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/client/me')
            ->assertStatus(403);
    }

    public function test_inactive_client_cannot_access_portal(): void
    {
        $company = Company::factory()->create([
            'status' => 'approved',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'client',
            'is_admin' => false,
            'is_active' => false,
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('client-portal')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/client/me')
            ->assertStatus(403);
    }

    public function test_client_with_unapproved_company_cannot_access_portal(): void
    {
        $company = Company::factory()->create([
            'status' => 'pending_review',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role' => 'client',
            'is_admin' => false,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('client-portal')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/client/me')
            ->assertStatus(403);
    }

    public function test_approved_verified_active_client_can_access_portal(): void
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

        $token = $user->createToken('client-portal')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/client/me')
            ->assertOk();
    }
}