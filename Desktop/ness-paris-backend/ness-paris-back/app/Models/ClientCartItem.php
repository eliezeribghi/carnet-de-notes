<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientCartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'cart_item_id',
        'product_id',
        'sku',
        'name',
        'color',
        'size',
        'price_cents',
        'price_type',
        'quantity',
        'image',
        'slug',
    ];

   protected $casts = [
    'cart_id' => 'integer',
    'product_id' => 'integer',
    'price_cents' => 'integer',
    'quantity' => 'integer',
];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(ClientCart::class, 'cart_id');
    }

    public function getPriceAttribute(): float
    {
        return $this->price_cents / 100;
    }
}
