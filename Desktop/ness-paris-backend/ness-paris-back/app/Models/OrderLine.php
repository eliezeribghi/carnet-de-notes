<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderLine extends Model
{
    protected $fillable = [
        'order_id', 'product_id',
        'name_snapshot', 'sku_snapshot', 'model_snapshot',
        'size_snapshot', 'color_snapshot', 'image_snapshot',
        'unit_price_cents', 'qty', 'line_total_cents',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUnitPriceEurosAttribute(): float
    {
        return $this->unit_price_cents / 100;
    }

    public function getLineTotalEurosAttribute(): float
    {
        return $this->line_total_cents / 100;
    }
}
