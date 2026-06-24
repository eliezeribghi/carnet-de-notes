<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantTag extends Model
{
    protected $fillable = ['product_id', 'tag'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
