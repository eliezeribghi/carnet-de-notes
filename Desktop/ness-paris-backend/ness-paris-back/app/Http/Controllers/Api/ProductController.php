<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Support\Facades\Log;
use App\Services\BarcodeService;  // ✅ AJOUTE ÇA
use App\Models\Gender;
use App\Models\Category;
use App\Models\Color;
use App\Models\Size;
use App\Services\Pricing\PriceResolver;

use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Liste paginée des produits avec filtres
     */
    public function index(ProductIndexRequest $request)
    {
        $query = Product::query()
            ->with(['brand', 'category', 'gender', 'color', 'size']);

        // Filtres par code (lisible pour le front)
        if ($gender = $request->query('gender')) {
            $query->whereHas('gender', fn ($q) => $q->where('code', $gender));
        }

        if ($category = $request->query('category')) {
            $query->whereHas('category', fn ($q) => $q->where('code', $category));
        }

        if ($color = $request->query('color')) {
            $query->whereHas('color', function ($q) use ($color) {
                $q->where('key', $color)
                  ->orWhere('slug', $color)
                  ->orWhere('display_name', $color);
            });
        }

        if ($size = $request->query('size')) {
            $query->whereHas('size', fn ($q) => $q->where('code', $size));
        }

        // Scopes enfant/adulte
        if ($request->boolean('kid_only')) {
            $query->kid();
        }

        if ($request->boolean('adult_only')) {
            $query->adult();
        }

        // Recherche texte
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('display_name', 'like', '%' . $search . '%')
                  ->orWhere('sku', 'like', '%' . $search . '%')
                  ->orWhere('reference_code', 'like', '%' . $search . '%');
            });
        }

        // Pagination sécurisée
       $perPage = (int) $request->query('per_page', 20);

            if ($perPage > 100) {
                $perPage = 100;
            } elseif ($perPage < 1) {
                $perPage = 20;
            }


        $products = $query
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return ProductResource::collection($products);
    }

    /**
     * Chercher un produit par code (reference_code ou sku)
     */
    public function byCode($code)
    {
        $product = Product::query()
            ->where('reference_code', $code)
            ->orWhere('sku', $code)
            ->first();

        if (!$product) {
            return response()->json([
                'error' => 'Produit non trouvé',
                'code_searched' => $code
            ], 404);
        }

        return new ProductResource(
            $product->load(['brand', 'category', 'gender', 'color', 'size'])
        );
    }

    /**
     * Afficher un produit spécifique
     */
public function show(Product $product)
{
    $product->load(['brand', 'category', 'gender', 'color', 'size']);

    return new ProductResource($product);
}

    /**
     * Mettre à jour le stock (increase/decrease/set)
     */
    public function updateStock(Request $request, Product $product)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0',
            'operation' => 'required|in:increase,decrease,set',
        ]);

        $qty = (int) $validated['quantity'];
        $operation = $validated['operation'];

        // Log l'opération (optionnel)
        Log::info("Stock update: {$operation} {$qty} pour product {$product->id}");

        if ($operation === 'increase') {
            $product->stock_quantity += $qty;
        } elseif ($operation === 'decrease') {
            $product->stock_quantity = max(0, $product->stock_quantity - $qty);
        } else { // 'set'
            $product->stock_quantity = $qty;
        }

        $product->save();

        return new ProductResource($product);
    }

    /**
     * Créer un produit
     */
       public function store(Request $request)
{
    Log::info('🚀 NOUVEAU STORE ACTIF !', ['data' => $request->all()]);

    $validated = $request->validate([
        'brand_id'       => 'required|exists:brands,id',
        'category_id'    => 'required|exists:categories,id',
        'gender_id'      => 'required|exists:genders,id',
        'color_id'       => 'required|exists:colors,id',
        'size_id'        => 'required|exists:sizes,id',
        'model_name'     => 'required|string|max:150',
        'display_name'   => 'required|string|max:200',
        'price'          => 'required|numeric|min:0',
        'stock_quantity' => 'required|integer|min:0',
    ]);

    Log::info('✅ Génération codes...');
    // ✅ NOUVEAU : Charge relations AVANT create
    $gender = Gender::findOrFail($validated['gender_id']);
    $category = Category::findOrFail($validated['category_id']);
    $color  = Color::findOrFail($validated['color_id']);

    $size   = Size::findOrFail($validated['size_id']);

    // ✅ Génération AVANT create
    $barcodeService = new BarcodeService();
    $validated['reference_code'] = $barcodeService->generate($gender, $color, $size);
    // ✅ TON FORMAT UNI-TSH-WHI-XXS
       // ✅ TON FORMAT UNI-TSH-WHI-XXS
        $validated['sku'] = strtoupper(
        substr($gender->code, 0, 3) . '-' .           // UNI
        substr($category->code, 0, 3) . '-' .         // TSH (tshirt)
        substr($color->key, 0, 3) . '-' .             // WHI (White)
        $size->code                                   // XXS
        );

    // Après génération SKU/ref
$validated['slug'] = \Illuminate\Support\Str::slug($validated['display_name']);

Log::info('✅ Codes complets:', $validated);



    // ✅ Create MAINTENANT OK
    $product = Product::create($validated);
     Log::info('✅ Codes complets:', $validated);


    return new ProductResource(
        $product->load(['brand', 'category', 'gender', 'color', 'size'])
    );
}







    /**
     * Mettre à jour un produit
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'model_name' => 'string|max:150',
            'display_name' => 'string|max:200',
            'price_retail_cents' => 'required|integer|min:0',
'price_pro_cents' => 'nullable|integer|min:0',
            'stock_quantity' => 'integer|min:0',
        ]);

        $product->update($validated);

        return new ProductResource($product);
    }

    /**
     * Supprimer un produit
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['message' => 'Produit supprimé'], 200);
    }
         /**
         * Top références (pour l'accueil)
         */
        public function topUsed(Request $request)
        {
            // nombre à renvoyer, par défaut 12
            $limit = (int) $request->query('limit', 12);
    Log::info('topUsed limit = ' . $limit);
            $products = Product::query()
                ->with(['brand', 'category', 'gender', 'color', 'size'])
                ->orderByDesc('id')      // ici tu peux changer la logique : ventes, popularité, etc.
                ->limit($limit)
                ->get();

            return ProductResource::collection($products);
        }

}
