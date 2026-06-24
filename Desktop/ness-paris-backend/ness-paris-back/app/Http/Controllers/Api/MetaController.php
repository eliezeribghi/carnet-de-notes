<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Gender;
use App\Models\Size;

class MetaController extends Controller
{
    public function index()
    {
        return response()->json([
            'brands' => Brand::select('id', 'name', 'city')->orderBy('name')->get(),
            'categories' => Category::select('id', 'code', 'label')->orderBy('label')->get(),
            'genders' => Gender::select('id', 'code', 'label')->orderBy('id')->get(),
            'colors' => Color::select('id', 'key', 'display_name', 'slug')->orderBy('display_name')->get(),
            'sizes' => Size::select('id', 'code', 'label', 'segment', 'sort_order')
                ->orderBy('segment')
                ->orderBy('sort_order')
                ->get(),
        ]);
    }
}
