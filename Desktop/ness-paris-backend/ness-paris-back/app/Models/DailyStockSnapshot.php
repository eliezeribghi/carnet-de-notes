<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyStockSnapshot extends Model
{
    use HasFactory;

    protected $table = 'daily_stock_snapshot';

    protected $fillable = [
        'product_id',
        'stock_quantity',
        'stock_date',
    ];

    protected $casts = [
        'stock_date' => 'date',
        'stock_quantity' => 'integer',
    ];

    // Relations
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
