<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockHistory;
use App\Models\Product;
use Illuminate\Http\Request;

class StockHistoryController extends Controller
{
    /**
     * Lister l'historique du stock d'un produit
     * GET /api/stock-history?product_id={id}
     */
    public function index(Request $request)
    {
        $productId = $request->query('product_id');

        if (!$productId) {
            return response()->json(['error' => 'product_id requis'], 400);
        }

        Product::findOrFail($productId);

        $history = StockHistory::where('product_id', $productId)
            ->with(['product', 'user'])
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($history);
    }

    /**
     * Obtenir un mouvement de stock spécifique
     * GET /api/stock-history/{id}
     */
    public function show($id)
    {
        $history = StockHistory::with(['product', 'user'])->findOrFail($id);

        return response()->json($history);
    }

    /**
     * Obtenir les statistiques de stock
     * GET /api/stock-history/stats/{product_id}
     */
    public function getStats($productId)
    {
        $product = Product::findOrFail($productId);

        $addedTotal = StockHistory::where('product_id', $productId)
            ->where('operation', 'add')
            ->sum('quantity');

        $removedTotal = StockHistory::where('product_id', $productId)
            ->where('operation', 'remove')
            ->sum('quantity');

        $lastMonth = StockHistory::where('product_id', $productId)
            ->where('created_at', '>=', now()->subMonth())
            ->get();

        return response()->json([
            'product_id' => $productId,
            'current_stock' => $product->stock_quantity,
            'total_added' => $addedTotal,
            'total_removed' => $removedTotal,
            'net_movement' => $addedTotal - $removedTotal,
            'last_month' => [
                'added' => $lastMonth->where('operation', 'add')->sum('quantity'),
                'removed' => $lastMonth->where('operation', 'remove')->sum('quantity'),
                'movements' => $lastMonth->count()
            ]
        ]);
    }

    /**
     * Exporter l'historique en CSV
     * GET /api/stock-history/export/{product_id}
     */
    public function export($productId)
    {
        Product::findOrFail($productId);

        $history = StockHistory::where('product_id', $productId)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        $csv = "Date,Heure,Opération,Quantité (colis),Pièces,Référence,Notes,Utilisateur\n";

        foreach ($history as $item) {
            $csv .= sprintf(
                '"%s","%s","%s",%d,%d,"%s","%s","%s"%s',
                $item->created_at->toDateString(),
                $item->created_at->format('H:i:s'),
                $item->operation === 'add' ? 'Ajout' : 'Retrait',
                $item->quantity,
                $item->quantity * 10,
                $item->reference ?? '',
                $item->notes ?? '',
                $item->user?->name ?? 'Système',
                "\n"
            );
        }

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="stock-history-' . $productId . '.csv"');
    }
}
