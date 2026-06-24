<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductGroup;
use Illuminate\Http\Request;

class ProductGroupController extends Controller
{
   public function index(Request $request)
{
     $groups = ProductGroup::with(['brand', 'category', 'gender', 'tags'])
        ->where('is_published', true)
        ->when($request->gender, function($q) use ($request) {
            $genders = array_filter(explode(',', $request->gender));
            $q->whereHas('gender', fn($g) => $g->whereIn('code', $genders));
        })
        ->when($request->age_group, function($q) use ($request) {
            $ageGroup = $request->age_group;
            $q->whereHas('products', fn($p) => $p->whereIn('age_group', [$ageGroup, 'both']));
        })
        ->when($request->category, fn($q) => $q->whereHas('category', fn($g) => $g->where('code', $request->category)))
        ->when($request->tag,      fn($q) => $q->whereHas('tags',     fn($g) => $g->where('tag', $request->tag)))
        ->get()
        ->map(fn($group) => $this->format($group));

    return response()->json($groups);
}

    public function show(string $slug)
    {
        $group = ProductGroup::with([
            'brand', 'category', 'gender', 'tags',
            'products.color', 'products.size', 'products.images'
        ])
        ->where('slug', $slug)
        ->where('is_published', true)
        ->firstOrFail();

        return response()->json($this->format($group, true));
    }

    private function format(ProductGroup $g, bool $withVariants = false): array
    {
        $data = [
            'id'          => $g->id,
            'name'        => $g->model_name,
            'subtitle'    => $g->subtitle,
            'slug'        => $g->slug,
            'description' => $g->description,
            'composition' => $g->composition,
            'base_price'  => $g->base_price,
            'sizes'       => $g->sizes,
            'brand'       => $g->brand?->name,
            'category'    => $g->category?->code,
            'gender'      => $g->gender?->code,
            'tags'        => $g->tags->pluck('tag'),
           'cover_image' => $g->products->first()?->images->first()
    ? asset('storage/' . ltrim($g->products->first()->images->first()->path, '/'))
    : null,
            'colors'      => $g->products->unique('color_id')->map(fn($p) => [
                'id'   => $p->color?->id,
                'key'  => $p->color?->key,
                'name' => $p->color?->display_name,
                'hex'  => $p->color?->hex,

                'cover' => $p->images->first()
    ? asset('storage/' . ltrim($p->images->first()->path, '/'))
    : null,
            ])->values(),
        ];

        if ($withVariants) {
    $data['variants'] = $g->products->map(fn($p) => [
    'id'                 => $p->id,
    'sku'                => $p->sku,
    'color'              => $p->color?->display_name,
    'color_key'          => $p->color?->key,
    'size'               => $p->size?->code,
    'stock'              => $p->stock_quantity,
    'price'              => $p->price,
    'price_retail_cents' => $p->price_retail_cents,
    'price_pro_cents'    => $p->price_pro_cents,
    'images'             => ($p->images instanceof \Illuminate\Support\Collection
        ? $p->images
        : collect()
    )->map(fn($img) => asset('storage/' . ltrim($img->path, '/')))->values(),
])->values();
        }

        return $data;
    }

    public function store(Request $request) { /* admin only */ }
    public function update(Request $request, ProductGroup $group) { /* admin only */ }
    public function destroy(ProductGroup $group) { /* admin only */ }
}
