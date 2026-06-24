<?php


namespace App\Http\Controllers\Api;

// ✅ AJOUTER CES IMPORTS (s'ils ne sont pas présents)
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesHistory;
use App\Models\StockHistory;
use App\Models\SalesByVariant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class SalesAnalyticsController extends Controller
{
    const PIECES_PER_UNIT = 10;

    /**
     * Obtenir les analytics complètes pour un produit
     * GET /api/products/{id}/sales-analytics
     */
    public function getSalesAnalytics($id)
    {
        $product = Product::with(['brand', 'category', 'color', 'size', 'gender'])->findOrFail($id);

        // Récupérer les ventes des 12 derniers mois
        $monthlyData = $this->getMonthlyData($id);

        // Récupérer les top variantes
        $topVariants = $this->getTopVariants($id);

        // Statistiques générales
        $stats = $this->getGeneralStats($id);

        return response()->json([
            'product' => $product,
            'monthly' => $monthlyData,
            'topVariants' => $topVariants,
            'stats' => $stats
        ]);
    }

    /**
     * Obtenir l'historique du stock
     * GET /api/products/{id}/stock-history
     */
    public function getStockHistory($id)
    {
        Product::findOrFail($id);

        $history = StockHistory::where('product_id', $id)

            ->with('user')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'type' => $item->operation,
                    'quantity' => $item->quantity,
                    'pieces' => $item->quantity * self::PIECES_PER_UNIT,
                    'reference' => $item->reference,
                    'notes' => $item->notes,
                    'user' => $item->user?->name ?? 'Système',
                    'date' => $item->created_at->toDateString(),
                    'time' => $item->created_at->format('H:i'),
                    'created_at' => $item->created_at->toJSON()

                ];
            });

        return response()->json($history);
    }

    /**
     * Ajouter/Retirer du stock
     * POST /api/products/{id}/stock-update
     */
    public function updateStock(Request $request, $id)  // ← CHANGER l'ordre des params
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:0|max:999',  // ← min:0 pour "set"
            'operation' => 'required|in:increase,decrease,set'  // ← Frontend values
        ]);

        $product = Product::findOrFail($id);
        $quantity = (int) $validated['quantity'];

        $operation = $validated['operation'];
        $delta = 0;

        switch ($operation) {
            case 'increase':
                $delta = $quantity;
                $newStock = $product->stock_quantity + $delta;
                break;
            case 'decrease':
                $delta = -$quantity;
                $newStock = max(0, $product->stock_quantity + $delta);  // Pas négatif
                break;
            case 'set':
                $delta = $quantity - $product->stock_quantity;
                $newStock = $quantity;
                break;
            default:
                return response()->json(['error' => 'Opération invalide'], 422);
        }

        // Mettre à jour le stock
        $product->update(['stock_quantity' => $newStock]);

        // Enregistrer dans l'historique
        StockHistory::create([
            'product_id' => $id,
            'operation' => $delta >= 0 ? 'add' : 'remove',
            'quantity' => abs($delta),
            'reference' => 'API: ' . ucfirst($operation),
            'notes' => 'Frontend: ' . $operation . ' ' . $quantity,
            'user_id' => Auth::id()
        ]);

        return response()->json([
            'success' => true,
            'id' => $product->id,
            'old_stock' => $product->stock_quantity - abs($delta),
            'new_stock' => $product->stock_quantity,
            'change' => $delta,
            'operation' => $operation,
            'message' => 'Stock mis à jour'
        ]);
    }


    /**
     * Enregistrer une vente
     * POST /api/products/{id}/record-sale
     */
public function recordSale(Request $request, $id)
{
    try {
        // Validation des données venant du front
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'order_number' => 'nullable|string|max:100',
            'customer_name' => 'nullable|string|max:150'
        ]);

        // Récupérer le produit
        $product = Product::with('salesByVariant')->findOrFail($id);

        // Vérifier le stock avant la vente
        if ($validated['quantity'] > $product->stock_quantity) {
            return response()->json(
                ['error' => 'Stock insuffisant pour cette vente'],
                422
            );
        }

        // Calculer le total
        $totalPrice = $validated['quantity'] * $validated['unit_price'];

        // Sauvegarder la vente
        $sale = SalesHistory::create([
            'product_id'   => $id,
            'quantity'     => $validated['quantity'],
            'unit_price'   => $validated['unit_price'],
            'total_price'  => $totalPrice,
            'order_number' => $validated['order_number'] ?? null,
            'customer_name'=> $validated['customer_name'] ?? null,
            'user_id'      => Auth::id() // peut être null si pas d’auth, c’est autorisé si la colonne l’autorise
        ]);

        // Calcul du stock restant AVANT mise à jour (pour l’envoyer proprement)
        $oldStock = $product->stock_quantity;
        $newStock = $oldStock - $validated['quantity'];

        // Mettre à jour le stock du produit
        $product->update([
            'stock_quantity' => $newStock
        ]);

        // Enregistrer dans l'historique du stock
        StockHistory::create([
            'product_id' => $id,
            'operation'  => 'remove',
            'quantity'   => $validated['quantity'],
            'reference'  => $validated['order_number'] ?? null,
            'notes'      => 'Vente - ' . ($validated['customer_name'] ?? 'Client'),
            'user_id'    => Auth::id()
        ]);

        // Mettre à jour les stats par variante
        $this->updateVariantStats($id, $validated['quantity'], $totalPrice);

        return response()->json([
            'id'              => $sale->id,
            'product_id'      => $id,
            'quantity'        => $validated['quantity'],
            'total_price'     => $totalPrice,
            'stock_old'       => $oldStock,
            'stock_remaining' => $newStock,
            'message'         => 'Vente enregistrée avec succès'
        ]);

    } catch (\Throwable $e) {
        // Pour déboguer le 500 proprement
        return response()->json([
            'error'   => 'Erreur serveur lors de l’enregistrement de la vente',
            'message' => $e->getMessage()
        ], 500);
    }
}



    /**
     * Helpers privés
     */

    private function getMonthlyData($productId)
    {
        $monthlyData = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $month = $date->format('M Y');

            $sales = SalesHistory::where('product_id', $productId)
                ->whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->get();

            $monthlyData[] = [
                'month' => $month,
                'quantity' => $sales->sum('quantity'),
                'revenue' => (float)$sales->sum('total_price'),
                'pieces' => $sales->sum('quantity') * self::PIECES_PER_UNIT,
                'orders' => $sales->count()
            ];
        }

        return $monthlyData;
    }

    private function getTopVariants($productId)
    {
        return SalesByVariant::where('product_id', $productId)
            ->with(['size', 'color', 'gender'])
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get()
            ->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'size' => $variant->size?->label ?? 'N/A',
                    'color' => $variant->color?->display_name ?? 'N/A',
                    'gender' => $variant->gender?->label ?? 'N/A',
                    'sales' => $variant->total_sold,
                    'pieces' => $variant->total_sold * self::PIECES_PER_UNIT,
                    'revenue' => (float)$variant->total_revenue,
                    'average_price' => $variant->average_price
                ];
            });
    }

    private function getGeneralStats($productId)
    {
        $allSales = SalesHistory::where('product_id', $productId)->get();
        $thisMonth = SalesHistory::where('product_id', $productId)->thisMonth()->get();
        $thisYear = SalesHistory::where('product_id', $productId)->thisYear()->get();

        return [
            'total_sold' => (int)$allSales->sum('quantity'),
            'total_revenue' => (float)$allSales->sum('total_price'),
            'total_pieces' => (int)$allSales->sum('quantity') * self::PIECES_PER_UNIT,
            'monthly_average' => $allSales->count() > 0
                ? round($allSales->sum('quantity') / 12, 1)
                : 0,
            'this_month' => [
                'quantity' => (int)$thisMonth->sum('quantity'),
                'revenue' => (float)$thisMonth->sum('total_price')
            ],
            'this_year' => [
                'quantity' => (int)$thisYear->sum('quantity'),
                'revenue' => (float)$thisYear->sum('total_price')
            ]
        ];
    }

    private function updateVariantStats($productId, $quantity, $totalPrice)
    {
        $product = Product::findOrFail($productId);

        $variant = SalesByVariant::firstOrCreate(
            [
                'product_id' => $productId,
                'size_id' => $product->size_id,
                'color_id' => $product->color_id,
                'gender_id' => $product->gender_id
            ]
        );

        $variant->increment('total_sold', $quantity);
        $variant->increment('total_revenue', $totalPrice);
    }
 public function getGlobalSalesAnalytics()
{
    // 12 derniers mois pour TOUS les produits
    $monthlyData = $this->getGlobalMonthlyData();

    // Stats globales (toutes ventes confondues)
    $stats = $this->getGlobalGeneralStats();

    return response()->json([
        'monthly' => $monthlyData,
        'stats'   => $stats,
    ]);
}
private function getGlobalMonthlyData()
{
    $monthlyData = [];

    for ($i = 11; $i >= 0; $i--) {
        $date = Carbon::now()->subMonths($i);

        $sales = SalesHistory::whereYear('created_at', $date->year)
            ->whereMonth('created_at', $date->month)
            ->get();

        $monthlyData[] = [
            'month'    => $date->format('Y-m'),
            'label'    => $date->translatedFormat('F Y'), // "janvier 2025"
            'quantity' => (int) $sales->sum('quantity'),
            'revenue'  => (float) $sales->sum('total_price'),
            'pieces'   => (int) $sales->sum('quantity') * self::PIECES_PER_UNIT,
            'orders'   => $sales->count(),
        ];
    }

    return $monthlyData;
}

private function getGlobalGeneralStats()
{
    $allSales  = SalesHistory::all();
    $thisMonth = SalesHistory::thisMonth()->get();
    $thisYear  = SalesHistory::thisYear()->get();

    return [
        'total_sold'    => (int) $allSales->sum('quantity'),
        'total_revenue' => (float) $allSales->sum('total_price'),
        'total_pieces'  => (int) $allSales->sum('quantity') * self::PIECES_PER_UNIT,
        'monthly_average' => $allSales->count() > 0
            ? round($allSales->sum('quantity') / 12, 1)
            : 0,
        'this_month' => [
            'quantity' => (int) $thisMonth->sum('quantity'),
            'revenue'  => (float) $thisMonth->sum('total_price'),
        ],
        'this_year' => [
            'quantity' => (int) $thisYear->sum('quantity'),
            'revenue'  => (float) $thisYear->sum('total_price'),
        ],
    ];
}


}
