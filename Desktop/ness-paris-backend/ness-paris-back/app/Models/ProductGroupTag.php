<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductGroupTag extends Model
{
    protected $table = 'product_group_tags';
    protected $fillable = ['product_group_id', 'tag'];
}
