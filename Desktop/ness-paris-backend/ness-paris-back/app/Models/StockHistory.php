<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockHistory extends Model
{
    protected $table = 'stock_history';
    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relations
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Scopes
    public function scopeRecent($query)
    {
        return $query->orderByDesc('created_at');
    }

    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeAdded($query)
    {
        return $query->where('operation', 'add');
    }

    public function scopeRemoved($query)
    {
        return $query->where('operation', 'remove');
    }

    // Méthodes utiles
    public function isAddition(): bool
    {
        return $this->operation === 'add';
    }

    public function isRemoval(): bool
    {
        return $this->operation === 'remove';
    }
}
