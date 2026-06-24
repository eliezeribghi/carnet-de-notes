<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    protected $fillable = [
    'name', 'legal_name', 'vat_number', 'siren', 'siret',
    'email', 'phone', 'country', 'status', 'is_active',
    'approved_at', 'approved_by',
    'address_line1', 'address_line2', 'postal_code', 'city',
    'shipping_address', 'shipping_city', 'shipping_zip', 'shipping_country',
    'billing_address', 'billing_city', 'billing_zip', 'billing_country',
];

    protected $casts = [
        'is_active' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isApproved(): bool
{
    return $this->status === self::STATUS_APPROVED && $this->is_active;
}

    public function isPendingReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }


public function orders(): HasMany
{
    return $this->hasMany(Order::class);
}

public function invoices(): HasMany
{
    return $this->hasMany(Invoice::class);
}
}
