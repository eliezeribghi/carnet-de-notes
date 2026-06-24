<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductGroup extends Model
{
    protected $fillable = [
        'brand_id', 'category_id', 'gender_id',
        'model_name', 'subtitle', 'slug', 'description',
        'composition', 'sizes', 'base_price',
        'seo_title', 'seo_description', 'og_image',
        'seo_canonical', 'seo_keywords', 'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'base_price'   => 'decimal:2',
        'sizes'        => 'array'
    ];

    public function brand(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function gender(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Gender::class);
    }

    public function tags(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductGroupTag::class);
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class);
    }
    // app/Models/ProductGroup.php — ajouter ces deux relations

public function groupSizes(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(ProductGroupSize::class);
}

public function sizes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    return $this->belongsToMany(Size::class, 'product_group_sizes')
                ->orderBy('sort_order');
}
}
