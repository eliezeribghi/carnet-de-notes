<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesByVariant extends Model
{
    protected $table = 'sales_by_variant';
    protected $guarded = ['id'];

    protected $casts = [
        'total_sold' => 'integer',
        'total_revenue' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public $timestamps = true;

    // Relations
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class, 'size_id');
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class, 'color_id');
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class, 'gender_id');
    }

    // Scopes
    public function scopeByProduct($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeTopSellers($query, $limit = 5)
    {
        return $query->orderByDesc('total_sold')->limit($limit);
    }

    // Accesseurs
    public function getAveragePriceAttribute(): float
    {
        return $this->total_sold > 0 ? (float)($this->total_revenue / $this->total_sold) : 0;
    }

    public function getTotalPiecesAttribute(): int
    {
        return $this->total_sold * 10; // 1 colis = 10 pièces
    }
}
