<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalesHistory;
use App\Models\Product;
use Illuminate\Http\Request;

class SalesHistoryController extends Controller
{
    /**
     * Lister l'historique des ventes d'un produit
     * GET /api/sales-history?product_id={id}
     */
    public function index(Request $request)
    {
        $productId = $request->query('product_id');

        if (!$productId) {
            return response()->json(['error' => 'product_id requis'], 400);
        }

        Product::findOrFail($productId);

        $sales = SalesHistory::where('product_id', $productId)
            ->with(['product', 'user'])
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($sales);
    }

    /**
     * Obtenir une vente spécifique
     * GET /api/sales-history/{id}
     */
    public function show($id)
    {
        $sale = SalesHistory::with(['product', 'user'])->findOrFail($id);

        return response()->json($sale);
    }

    /**
     * Obtenir les statistiques de ventes
     * GET /api/sales-history/stats/{product_id}
     */
    public function getStats($productId)
    {
        $product = Product::findOrFail($productId);

        $allSales = SalesHistory::where('product_id', $productId)->get();
        $thisMonth = SalesHistory::where('product_id', $productId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->get();
        $thisYear = SalesHistory::where('product_id', $productId)
            ->whereYear('created_at', now()->year)
            ->get();

        $totalSold = $allSales->sum('quantity');

        return response()->json([
            'product_id' => $productId,
            'all_time' => [
                'total_sold' => (int)$totalSold,
                'total_revenue' => (float)$allSales->sum('total_price'),
                'total_pieces' => (int)$totalSold * 10,
                'average_price' => $totalSold > 0 ? (float)($allSales->sum('total_price') / $totalSold) : 0,
                'orders' => $allSales->count()
            ],
            'this_month' => [
                'total_sold' => (int)$thisMonth->sum('quantity'),
                'total_revenue' => (float)$thisMonth->sum('total_price'),
                'total_pieces' => (int)$thisMonth->sum('quantity') * 10,
                'orders' => $thisMonth->count()
            ],
            'this_year' => [
                'total_sold' => (int)$thisYear->sum('quantity'),
                'total_revenue' => (float)$thisYear->sum('total_price'),
                'total_pieces' => (int)$thisYear->sum('quantity') * 10,
                'orders' => $thisYear->count()
            ],
            'monthly_average' => $allSales->count() > 0 ? round($totalSold / 12, 1) : 0
        ]);
    }

    /**
     * Exporter l'historique des ventes en CSV
     * GET /api/sales-history/export/{product_id}
     */
    public function export($productId)
    {
        Product::findOrFail($productId);

        $sales = SalesHistory::where('product_id', $productId)
            ->with('user')
            ->orderByDesc('created_at')
            ->get();

        $csv = "Date,Heure,Quantité (colis),Pièces,Prix unitaire,Total,Numéro commande,Client,Utilisateur\n";

        foreach ($sales as $sale) {
            $csv .= sprintf(
                '"%s","%s",%d,%d,%.2f,%.2f,"%s","%s","%s"%s',
                $sale->created_at->toDateString(),
                $sale->created_at->format('H:i:s'),
                $sale->quantity,
                $sale->quantity * 10,
                $sale->unit_price,
                $sale->total_price,
                $sale->order_number ?? '',
                $sale->customer_name ?? '',
                $sale->user?->name ?? 'Système',
                "\n"
            );
        }

        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="sales-history-' . $productId . '.csv"');
    }

    /**
     * Rapport des ventes par période
     * GET /api/sales-history/report/{product_id}?from={date}&to={date}
     */
    public function reportByPeriod($productId, Request $request)
    {
        Product::findOrFail($productId);

        $from = $request->query('from', now()->subMonths(12)->toDateString());
        $to = $request->query('to', now()->toDateString());

        $sales = SalesHistory::where('product_id', $productId)
            ->whereBetween('created_at', [$from, $to])
            ->get();

        // Grouper par jour
        $byDay = [];
        foreach ($sales as $sale) {
            $day = $sale->created_at->toDateString();
            if (!isset($byDay[$day])) {
                $byDay[$day] = [
                    'quantity' => 0,
                    'revenue' => 0,
                    'orders' => 0
                ];
            }
            $byDay[$day]['quantity'] += $sale->quantity;
            $byDay[$day]['revenue'] += $sale->total_price;
            $byDay[$day]['orders'] += 1;
        }

        return response()->json([
            'product_id' => $productId,
            'period' => [
                'from' => $from,
                'to' => $to
            ],
            'summary' => [
                'total_sold' => (int)$sales->sum('quantity'),
                'total_revenue' => (float)$sales->sum('total_price'),
                'total_orders' => $sales->count(),
                'average_per_order' => $sales->count() > 0 ? (float)($sales->sum('total_price') / $sales->count()) : 0
            ],
            'by_day' => $byDay
        ]);
    }
}
