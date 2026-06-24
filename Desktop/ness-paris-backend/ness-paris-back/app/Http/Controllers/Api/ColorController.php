<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Color;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ColorController extends Controller
{
    /**
     * GET /api/colors
     * Retourne toutes les couleurs du référentiel.
     * Public — pas d'auth requise (données non sensibles).
     */
    public function index()
    {
        $colors = Color::select('id', 'key', 'slug', 'display_name', 'hex', 'is_grey')
            ->orderBy('display_name')
            ->get();

        return response()->json(['data' => $colors]);
    }

    /**
     * POST /api/colors
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'hex'          => ['nullable', 'string', 'max:7'],
            'is_grey'      => ['nullable', 'boolean'],
        ]);

        $displayName = $data['display_name'];

        $color = Color::create([
            'display_name' => $displayName,
            'key'          => $displayName,
            'slug'         => Str::slug($displayName),
            'hex'          => $data['hex'] ?? '#000000',
            'is_grey'      => $data['is_grey'] ?? false,
        ]);

        return response()->json(['data' => $color], 201);
    }
}
 // 'barcode_index' => (Color::count() + 1),
