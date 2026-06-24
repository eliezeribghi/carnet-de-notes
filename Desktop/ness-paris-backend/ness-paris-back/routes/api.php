<?php

// =============================================================================
// routes/api.php
//
// ORGANISATION :
//   1. Health check
//   2. Auth publique backoffice (login, forgot, reset)
//   3. Catalogue public (produits, meta)
//   4. Code-barres
//   5. User authentifié (backoffice)
//   6. Analytics & Stock
//   7. Métadonnées
//   8. Backoffice (CRUD users)
//   9. Groupes produits
//   10. Stripe webhook
//   11. Auth client B2B (register, login, forgot, reset) — public
//   12. Auth backoffice (admin + employee) — public
//   13. Espace client B2B — protégé auth:sanctum + client.portal
//       ├── Profil & company (me, company, lookup SIRET)
//       ├── Dashboard
//       ├── Commandes
//       ├── Factures (source Pennylane)
//       └── Panier & checkout
//   14. Espace backoffice — protégé auth:sanctum + backoffice.portal
//
// MIDDLEWARES CLÉS :
//   auth:sanctum      → token Sanctum Bearer valide
//   client.portal     → role=client + is_active + company approved
//   backoffice.portal → role=admin|employee + is_active
//   admin             → role=admin uniquement
//   throttle:5,1      → 5 requêtes/minute (routes sensibles : login, register)
//   throttle:10,1     → 10 requêtes/minute (lookup SIRET)
// =============================================================================

use App\Http\Controllers\Api\BarcodeController;
use App\Http\Controllers\Api\BackofficeAuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ClientAccountController;
use App\Http\Controllers\Api\ClientAuthController;
use App\Http\Controllers\Api\ColorController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductGroupController;
use App\Http\Controllers\Api\SalesAnalyticsController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// =============================================================================
// 1. HEALTH CHECK
// =============================================================================
Route::get('/health', fn () => response()->json(['status' => 'ok']));

// =============================================================================
// 2. AUTH PUBLIQUE BACKOFFICE
// =============================================================================
Route::prefix('backoffice')->group(function () {
    Route::post('/login', [BackofficeAuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::post('/forgot-password/email', [BackofficeAuthController::class, 'forgotPasswordByEmail'])
        ->middleware('throttle:3,1');

    Route::post('/reset-password/email', [BackofficeAuthController::class, 'resetPasswordByEmail'])
        ->middleware('throttle:3,1');




});

// =============================================================================
// 3. CATALOGUE PUBLIC
// =============================================================================
Route::prefix('products')->group(function () {
    Route::get('/{product}', [ProductController::class, 'show']);

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::put('/{product}', [ProductController::class, 'update']);
        Route::delete('/{product}', [ProductController::class, 'destroy']);
        Route::patch('/{product}/stock', [ProductController::class, 'updateStock']);
    });
});

Route::get('/meta', [MetaController::class, 'index']);

// =============================================================================
// 4. CODE-BARRES
// =============================================================================
Route::get('/barcodes', [BarcodeController::class, 'index']);
Route::get('/barcode/{product}', [BarcodeController::class, 'show']);
Route::post('/barcodes/mark-printed', [BarcodeController::class, 'markPrinted']);
Route::delete('/barcodes/reset-history', [BarcodeController::class, 'resetPrintHistory']);

// =============================================================================
// 5. USER AUTHENTIFIÉ (backoffice interne)
// =============================================================================
Route::middleware(['auth:sanctum', 'backoffice.portal'])->group(function () {
    Route::get('/user', [UserController::class, 'show']);
    Route::post('/logout', [BackofficeAuthController::class, 'logout']);
    Route::post('/user/update-password', [UserController::class, 'updatePassword']);
});

// =============================================================================
// 6. ANALYTICS & STOCK
// =============================================================================
Route::prefix('products/{id}')->group(function () {
    Route::get('/sales-analytics', [SalesAnalyticsController::class, 'getSalesAnalytics']);
    Route::get('/stock-history', [SalesAnalyticsController::class, 'getStockHistory']);
    Route::post('/stock-update', [SalesAnalyticsController::class, 'updateStock']);
    Route::post('/record-sale', [SalesAnalyticsController::class, 'recordSale']);
});

Route::get('/sales-analytics', [SalesAnalyticsController::class, 'getGlobalSalesAnalytics']);

// =============================================================================
// 7. MÉTADONNÉES
// =============================================================================
Route::get('/colors', [ColorController::class, 'index']);
Route::post('/colors', [ColorController::class, 'store']);
Route::post('/categories', [CategoryController::class, 'store']);

// =============================================================================
// 8. BACKOFFICE (CRUD users)
// =============================================================================
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
});

// =============================================================================
// 9. GROUPES PRODUITS
// =============================================================================
Route::prefix('product-groups')->group(function () {
    Route::get('/', [ProductGroupController::class, 'index']);
    Route::get('/{slug}', [ProductGroupController::class, 'show']);

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::post('/', [ProductGroupController::class, 'store']);
        Route::put('/{group}', [ProductGroupController::class, 'update']);
        Route::delete('/{group}', [ProductGroupController::class, 'destroy']);
    });
});

// =============================================================================
// 10. STRIPE WEBHOOK
// Pas de middleware auth — Stripe signe ses requêtes avec un secret webhook
// La vérification est faite dans StripeWebhookController::handle()
// =============================================================================
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

// =============================================================================
// 11. AUTH CLIENT B2B — routes PUBLIQUES
// throttle:5,1 = 5 requêtes par minute (anti brute-force)
// =============================================================================
Route::prefix('client')->group(function () {
    Route::post('/register', [ClientAuthController::class, 'register'])
        ->middleware('throttle:5,1');

    Route::post('/login', [ClientAuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::post('/forgot-password/email', [ClientAuthController::class, 'forgotPassword'])
        ->middleware('throttle:5,1');

    Route::post('/reset-password/email', [ClientAuthController::class, 'resetPassword'])
        ->middleware('throttle:3,1');
});

// =============================================================================
// 12. ESPACE CLIENT B2B — routes PROTÉGÉES
// auth:sanctum   → token Bearer valide
// client.portal  → role=client + is_active + company status géré
// =============================================================================
Route::prefix('client')->middleware(['auth:sanctum', 'client.portal'])->group(function () {
    // ── Profil client ────────────────────────────────────────────────────
    // GET  /client/me        → profil user + company enrichi Pennylane
    // POST /client/logout    → révoque le token Sanctum courant
    Route::get('/me', [ClientAccountController::class, 'me']);
    Route::post('/logout', [ClientAuthController::class, 'logout']);

    // ── Dashboard ────────────────────────────────────────────────────────
    // GET /client/dashboard  → stats + commandes/factures récentes
    Route::get('/dashboard', [ClientAccountController::class, 'dashboard']);

    // ── Commandes ────────────────────────────────────────────────────────
    // GET   /client/orders                → liste toutes les commandes
    // GET   /client/orders/{order}        → détail d'une commande
    // PATCH /client/orders/{order}/invoice → corrige les infos de facturation
    Route::get('/orders', [ClientAccountController::class, 'orders']);
    Route::get('/orders/{order}', [ClientAccountController::class, 'showOrder']);
    Route::patch('/orders/{order}/invoice', [ClientAccountController::class, 'updateOrderInvoice']);

    // ── Factures — source de vérité : Pennylane ──────────────────────────
    // GET /client/invoices         → liste les factures (depuis pennylane_invoice_id)
    // GET /client/invoices/{id}     → détail d'une facture
    Route::get('/invoices', [ClientAccountController::class, 'invoices']);
    Route::get('/invoices/{invoice}', [ClientAccountController::class, 'showInvoice']);

    // ── Company ──────────────────────────────────────────────────────────
    // GET  /client/company            → données company (enrichi Pennylane)
    // POST /client/company            → crée une company (si pas encore liée)
    // PUT  /client/company            → met à jour company + sync Pennylane
    // GET  /client/company/lookup     → lookup SIRET via INSEE
    //                                   pour pré-remplir le formulaire inscription
    // throttle:10,1 = 10 req/min (évite les abus INSEE)
    Route::get('/company', [ClientAccountController::class, 'company']);
    Route::post('/company', [ClientAccountController::class, 'createCompany']);
    Route::put('/company', [ClientAccountController::class, 'updateCompany']);
    Route::get('/company/lookup', [ClientAccountController::class, 'lookupSiret'])
        ->middleware('throttle:10,1');

    // ── Panier ───────────────────────────────────────────────────────────
    // GET    /client/cart                  → lire le panier (+ repricing)
    // POST   /client/cart/sync             → sync localStorage → serveur
    // POST   /client/cart/add              → ajouter un article
    // PATCH  /client/cart/{cartItemId}     → modifier quantité
    // DELETE /client/cart/{cartItemId}     → supprimer un article
    // DELETE /client/cart                  → vider le panier
    // GET    /client/cart/shipping-options → options livraison (GET + query params)
    //
    // IMPORTANT : /cart/shipping-options DOIT être défini AVANT /cart/{cartItemId}
    // sinon Laravel résout "shipping-options" comme un cartItemId
    Route::get('/cart/shipping-options', [CartController::class, 'shippingOptions']);
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/sync', [CartController::class, 'sync']);
    Route::post('/cart/add', [CartController::class, 'add']);
    Route::patch('/cart/{cartItemId}', [CartController::class, 'updateQuantity']);
    Route::delete('/cart/{cartItemId}', [CartController::class, 'remove']);
    Route::delete('/cart', [CartController::class, 'clear']);

    // ── Checkout ─────────────────────────────────────────────────────────
    // POST /client/cart/checkout        → formulaire classique → Stripe Session
    // POST /client/cart/apple-pay-intent → Apple Pay / Google Pay → PaymentIntent
    Route::post('/cart/checkout', [CartController::class, 'checkout']);
    Route::post('/cart/apple-pay-intent', [CartController::class, 'applePayIntent']);
});