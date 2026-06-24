<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductGroupSize extends Model
{
    public $timestamps = false;
    protected $table = 'product_group_sizes';
    protected $fillable = ['product_group_id', 'size_id'];

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class);
    }
}
