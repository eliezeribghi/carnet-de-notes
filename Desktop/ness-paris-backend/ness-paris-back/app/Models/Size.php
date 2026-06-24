<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Size extends Model
{
    protected $fillable = [
        'code',
        'label',
        'segment',
        'sort_order',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
    // app/Models/Size.php
protected $casts = [
    'sort_order' => 'integer',
];

// Scopes utiles pour filtrer par segment
public function scopeKid($query)
{
    return $query->where('segment', 'KID')->orderBy('sort_order');
}

public function scopeAdult($query)
{
    return $query->where('segment', 'ADULT')->orderBy('sort_order');
}
}
