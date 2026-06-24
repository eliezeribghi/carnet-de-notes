<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
     use HasFactory;
    protected $fillable = [
        'product_group_id',
        'brand_id',
        'category_id',
        'gender_id',
        'color_id',
        'size_id',
        'model_name',
        'display_name',
        'sku',
        'reference_code',
        'barcode_value',
        'ean13',
        'price',
        'price_retail_cents',
        'price_pro_cents',
        'stock_quantity',
        'slug',
        'seo_title',
        'seo_description',
        'seo_canonical',
        'age_group',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_retail_cents' => 'integer',
            'price_pro_cents' => 'integer',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
            'age_group' => 'string',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function gender(): BelongsTo
    {
        return $this->belongsTo(Gender::class);
    }

    public function color(): BelongsTo
    {
        return $this->belongsTo(Color::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function salesByVariant(): HasMany
    {
        return $this->hasMany(SalesByVariant::class, 'product_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }

    public function scopeKid($query)
    {
        return $query->where('age_group', 'kid');
    }

    public function scopeAdult($query)
    {
        return $query->where('age_group', 'adult');
    }

    public function scopeBoth($query)
    {
        return $query->where('age_group', 'both');
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('reference_code', $code)
            ->orWhere('barcode_value', $code)
            ->orWhere('sku', $code);
    }

  public function hasRetailPrice(): bool
{
    return $this->price_retail_cents !== null;
}

public function hasProPrice(): bool
{
    return $this->price_pro_cents !== null;
}

public function retailPriceInEuros(): ?float
{
    return $this->price_retail_cents !== null
        ? $this->price_retail_cents / 100
        : null;
}

public function proPriceInEuros(): ?float
{
    return $this->price_pro_cents !== null
        ? $this->price_pro_cents / 100
        : null;
}
}
