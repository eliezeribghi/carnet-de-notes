<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gender extends Model
{
    protected $fillable = [
        'code',
        'label',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
