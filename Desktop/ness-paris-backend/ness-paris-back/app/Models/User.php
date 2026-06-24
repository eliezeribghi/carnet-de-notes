<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\ResetPasswordNotification;


class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    use HasFactory, HasApiTokens, Notifiable;

    protected $fillable = [
        'pennylane_customer_id', 
        'company_id',
        'name',
        'email',
        'password',
        'is_admin',
        'role',
        'is_active',
        'last_login_at',
         'phone',  
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime', 
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_admin'          => 'boolean',
            'is_active'         => 'boolean',
            'last_login_at'     => 'datetime',
        ];
    }

    // =========================================================================
    // Relations
    // =========================================================================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // =========================================================================
    // Filament — restreint l'accès au panel admin aux users is_admin = true
    // =========================================================================

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin === true;
    }

    // =========================================================================
    // Role helpers
    // =========================================================================

    public function isEmployee(): bool
    {
        return in_array($this->role, ['employee', 'admin'], true);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin || $this->role === 'admin';
    }

    public function isClient(): bool
    {
        return $this->role === 'client';
    }

    public function isInternalUser(): bool
    {
        return in_array($this->role, ['admin', 'user'], true);
    }

    // =========================================================================
    // Pricing & portal access
    // =========================================================================

    public function canAccessClientPortal(): bool
    {
        return $this->isClient() && $this->is_active;
    }

    public function hasApprovedCompany(): bool
    {
        return (bool) ($this->company && $this->company->isApproved());
    }

    public function canAccessProPricing(): bool
    {
        return $this->canAccessClientPortal() && $this->hasApprovedCompany();
    }
    /**
     * Override : on utilise notre notification FR brandée plutôt que celle par défaut.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
