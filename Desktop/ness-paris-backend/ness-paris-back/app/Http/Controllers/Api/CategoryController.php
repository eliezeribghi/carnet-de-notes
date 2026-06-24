<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function store(Request $request)
    {

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'code'  => ['nullable', 'string', 'max:50'],
        ]);

        $label = $data['label'];
        // Vérifier unicité du code
            $code = $data['code'] ?? Str::slug($label, '_');
            $counter = 1;
         while (Category::where('code', $code)->exists()) {
                $code .= '_' . $counter++;
            }

        $category = Category::create([
            'label' => $label,
            'code'  => $data['code'] ?? Str::slug($label, '_'), // ex "sweat_col_rond"
        ]);

        return response()->json([
            'data' => $category,
        ], 201);

    }
}
