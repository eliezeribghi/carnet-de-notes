<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyStockSnapshot;
use App\Models\Product;
use Illuminate\Http\Request;

class DailyStockSnapshotController extends Controller
{
    /**
     * Créer un snapshot quotidien du stock
     * POST /api/daily-stock-snapshot/create
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'stock_date' => 'nullable|date'
        ]);

        $productId = $validated['product_id'];
        $stockDate = $validated['stock_date'] ?? now()->toDateString();

        $product = Product::findOrFail($productId);

        // Vérifier si un snapshot existe déjà pour ce jour
        $existing = DailyStockSnapshot::where('product_id', $productId)
            ->where('stock_date', $stockDate)
            ->first();

        if ($existing) {
            // Mettre à jour
            $existing->update(['stock_quantity' => $product->stock_quantity]);
            return response()->json([
                'message' => 'Snapshot mis à jour',
                'snapshot' => $existing
            ]);
        }

        // Créer un nouveau snapshot
        $snapshot = DailyStockSnapshot::create([
            'product_id' => $productId,
            'stock_quantity' => $product->stock_quantity,
            'stock_date' => $stockDate
        ]);

        return response()->json([
            'message' => 'Snapshot créé',
            'snapshot' => $snapshot
        ], 201);
    }

    /**
     * Obtenir l'historique des snapshots
     * GET /api/daily-stock-snapshot?product_id={id}&from={date}&to={date}
     */
    public function index(Request $request)
    {
        $productId = $request->query('product_id');
        $from = $request->query('from');
        $to = $request->query('to');

        if (!$productId) {
            return response()->json(['error' => 'product_id requis'], 400);
        }

        Product::findOrFail($productId);

        $query = DailyStockSnapshot::where('product_id', $productId);

        if ($from) {
            $query->where('stock_date', '>=', $from);
        }

        if ($to) {
            $query->where('stock_date', '<=', $to);
        }

        $snapshots = $query->orderByDesc('stock_date')->paginate(100);

        return response()->json($snapshots);
    }

    /**
     * Obtenir un snapshot spécifique
     * GET /api/daily-stock-snapshot/{id}
     */
    public function show($id)
    {
        $snapshot = DailyStockSnapshot::findOrFail($id);

        return response()->json($snapshot);
    }

    /**
     * Obtenir le snapshot du jour pour un produit
     * GET /api/daily-stock-snapshot/today/{product_id}
     */
    public function today($productId)
    {
        Product::findOrFail($productId);

        $snapshot = DailyStockSnapshot::where('product_id', $productId)
            ->where('stock_date', now()->toDateString())
            ->first();

        if (!$snapshot) {
            return response()->json(['error' => 'Pas de snapshot pour aujourd\'hui'], 404);
        }

        return response()->json($snapshot);
    }

    /**
     * Analyser la tendance du stock
     * GET /api/daily-stock-snapshot/trend/{product_id}?days=30
     */
    public function trend($productId, Request $request)
    {
        Product::findOrFail($productId);

        $days = $request->query('days', 30);
        $fromDate = now()->subDays($days)->toDateString();

        $snapshots = DailyStockSnapshot::where('product_id', $productId)
            ->where('stock_date', '>=', $fromDate)
            ->orderBy('stock_date')
            ->get();

        if ($snapshots->isEmpty()) {
            return response()->json(['error' => 'Pas de données'], 404);
        }

        $min = $snapshots->min('stock_quantity');
        $max = $snapshots->max('stock_quantity');
        $avg = round($snapshots->avg('stock_quantity'), 1);
        $current = $snapshots->last()->stock_quantity;
        $trend = $current - $snapshots->first()->stock_quantity;

        return response()->json([
            'product_id' => $productId,
            'period_days' => $days,
            'statistics' => [
                'min' => (int)$min,
                'max' => (int)$max,
                'average' => $avg,
                'current' => (int)$current,
                'trend' => (int)$trend,
                'trend_direction' => $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'flat')
            ],
            'data' => $snapshots->map(function ($snapshot) {
                return [
                    'date' => $snapshot->stock_date,
                    'quantity' => $snapshot->stock_quantity,
                    'pieces' => $snapshot->stock_quantity * 10
                ];
            })
        ]);
    }

    /**
     * Créer les snapshots quotidiens pour tous les produits
     * POST /api/daily-stock-snapshot/generate-all
     * (À exécuter une fois par jour via un cron job)
     */
    public function generateAll()
    {
        $today = now()->toDateString();
        $products = Product::all();

        $created = 0;
        $updated = 0;

        foreach ($products as $product) {
            $snapshot = DailyStockSnapshot::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'stock_date' => $today
                ],
                [
                    'stock_quantity' => $product->stock_quantity
                ]
            );

            $snapshot->wasRecentlyCreated ? $created++ : $updated++;
        }

        return response()->json([
            'message' => 'Snapshots générés',
            'created' => $created,
            'updated' => $updated,
            'total' => $products->count(),
            'date' => $today
        ]);
    }
}
