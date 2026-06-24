<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Color extends Model
{
    public $timestamps = false; // ✅ désactive created_at / updated_at

    protected $fillable = [
        'key',
        'display_name',
        'slug',
        'hex',
        'is_grey',
        
    ];

    protected $casts = [
        'is_grey' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
