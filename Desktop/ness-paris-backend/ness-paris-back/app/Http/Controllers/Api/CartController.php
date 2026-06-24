<?php

// =============================================================================
// app/Http/Controllers/Api/CartController.php
// CONTRÔLEUR PANIER — Ness Paris B2B
//
// RESPONSABILITÉ :
//   Gérer toutes les opérations sur le panier client et le processus de commande.
//   Ce contrôleur est le point d'entrée de TOUTES les actions panier/checkout.
//
// ROUTES GÉRÉES (voir routes/api.php) :
//   GET    /client/cart                      → index()         Lire le panier
//   POST   /client/cart/sync                 → sync()          Synchroniser panier local → serveur
//   POST   /client/cart/add                  → add()           Ajouter un article
//   PATCH  /client/cart/{cartItemId}         → updateQuantity() Modifier quantité
//   DELETE /client/cart/{cartItemId}         → remove()        Supprimer un article
//   DELETE /client/cart                      → clear()         Vider le panier
//   GET    /client/cart/shipping-options     → shippingOptions() Options de livraison
//   POST   /client/cart/checkout             → checkout()      Créer commande + session Stripe
//   POST   /client/cart/apple-pay-intent     → applePayIntent() Créer PaymentIntent Apple Pay
//
// DÉPENDANCES :
//   CartService    → logique métier panier (repricing, sync, formatage)
//   ShippingService → calcul poids et options de livraison
//   SendcloudService → enrichissement avec IDs méthodes Sendcloud
//   Stripe SDK     → création sessions et PaymentIntents
//
// AUTHENTIFICATION :
//   Toutes ces routes nécessitent auth:sanctum + middleware client.portal
//   (défini dans routes/api.php)
// =============================================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Product;
use App\Services\Cart\CartService;
use App\Services\SendcloudService;
use App\Services\ShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CartController extends Controller
{
    // -------------------------------------------------------------------------
    // INJECTION DE DÉPENDANCES
    // Le CartService est injecté par le container Laravel (IoC).
    // readonly = impossible de réassigner après construction (bonne pratique PHP 8.1+)
    // -------------------------------------------------------------------------
    public function __construct(
        private readonly CartService $cartService
    ) {}


    // =========================================================================
    // MÉTHODES CRUD PANIER
    // =========================================================================

    /**
     * Retourne le contenu du panier du client connecté.
     *
     * FLUX :
     *   1. Récupère ou crée le panier en BDD pour cet utilisateur
     *   2. Reprice les articles (applique les tarifs pro si le client est approuvé)
     *   3. Formate et retourne les items
     *
     * POURQUOI repriceCart() à chaque lecture ?
     *   Les prix peuvent changer côté admin entre deux sessions.
     *   On s'assure que le client voit toujours les prix à jour.
     *   Ex : un admin baisse le prix d'un article → le panier se met à jour
     *        à la prochaine ouverture.
     *
     * @return JsonResponse { items: CartItem[] }
     */
    public function index(Request $request): JsonResponse
    {
        $cart = $this->cartService->getOrCreateCart($request->user());
        $cart = $this->cartService->repriceCart($cart, $request->user());

        return response()->json([
            'items' => $this->cartService->formatItems($cart),
        ]);
    }

    /**
     * Synchronise le panier local (localStorage) vers le panier serveur.
     *
     * CAS D'USAGE :
     *   Quand un client se connecte après avoir ajouté des articles en mode anonyme,
     *   on fusionne le panier localStorage avec le panier serveur.
     *
     * VALIDATION :
     *   cartId     → identifiant unique côté frontend (UUID ou hash)
     *   productId  → nullable car certains articles peuvent ne plus exister
     *   sku        → référence produit
     *   quantity   → min:1 (on ne peut pas avoir 0 article)
     *
     * @return JsonResponse { items: CartItem[] }
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'             => ['required', 'array'],
            'items.*.cartId'    => ['required', 'string'],
            'items.*.productId' => ['nullable'],
            'items.*.sku'       => ['nullable', 'string'],
            'items.*.quantity'  => ['required', 'integer', 'min:1'],
            'items.*.color'     => ['nullable', 'string'],
            'items.*.size'      => ['nullable', 'string'],
            'items.*.image'     => ['nullable', 'string'],
        ]);

        $cart = $this->cartService->getOrCreateCart($request->user());
        $cart = $this->cartService->syncItems($cart, $validated['items'], $request->user());

        return response()->json([
            'items' => $this->cartService->formatItems($cart),
        ]);
    }

    /**
     * Ajoute un article au panier.
     *
     * NOTE : Si l'article (même cartId) existe déjà, CartService incrémente
     * la quantité au lieu d'en créer un doublon.
     *
     * @return JsonResponse { items: CartItem[] } HTTP 201
     */
    public function add(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cartId'    => ['required', 'string'],
            'productId' => ['nullable'],
            'sku'       => ['nullable', 'string'],
            'quantity'  => ['required', 'integer', 'min:1'],
            'color'     => ['nullable', 'string'],
            'size'      => ['nullable', 'string'],
            'image'     => ['nullable', 'string'],
        ]);

        $cart = $this->cartService->getOrCreateCart($request->user());
        $cart = $this->cartService->addItem($cart, $validated, $request->user());

        return response()->json([
            'items' => $this->cartService->formatItems($cart),
        ], 201);
    }

    /**
     * Met à jour la quantité d'un article du panier.
     *
     * @param cartItemId - Identifiant de l'article dans le panier (cart_item_id en BDD)
     * @return JsonResponse { items: CartItem[] }
     */
    public function updateQuantity(Request $request, string $cartItemId): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $cart = $this->cartService->getOrCreateCart($request->user());
        $cart = $this->cartService->updateQuantity(
            $cart,
            $cartItemId,
            (int) $validated['quantity'],
            $request->user()
        );

        return response()->json([
            'items' => $this->cartService->formatItems($cart),
        ]);
    }

    /**
     * Supprime un article du panier.
     *
     * @param cartItemId - Identifiant de l'article à supprimer
     * @return JsonResponse { items: CartItem[] }
     */
    public function remove(Request $request, string $cartItemId): JsonResponse
    {
        $cart = $this->cartService->getOrCreateCart($request->user());
        $cart = $this->cartService->removeItem($cart, $cartItemId, $request->user());

        return response()->json([
            'items' => $this->cartService->formatItems($cart),
        ]);
    }

    /**
     * Vide complètement le panier.
     *
     * Utilisé après un paiement réussi (webhook Stripe) ou manuellement par le client.
     *
     * @return JsonResponse { items: [] }
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartService->getOrCreateCart($request->user());
        $this->cartService->clear($cart);

        return response()->json(['items' => []]);
    }


    // =========================================================================
    // OPTIONS DE LIVRAISON
    // =========================================================================

    /**
     * Retourne les options de livraison disponibles pour le panier actuel.
     *
     * ENDPOINT : GET /client/cart/shipping-options
     * MÉTHODE  : GET (paramètres dans l'URL via query string, pas de body)
     *
     * PARAMÈTRES ATTENDUS (query string) :
     *   country     → code ISO 2 obligatoire (ex: "FR")
     *   subtotal    → sous-total HT en euros float (ex: "47.5")
     *   postal_code → code postal (ex: "93500") — utilisé pour le calcul du poids/zone
     *
     * FLUX :
     *   1. Calcule le poids total du panier (ShippingService::calculateCartWeight)
     *   2. Récupère les options depuis ShippingService (Sendcloud + fallback hardcodé)
     *   3. Enrichit chaque option avec l'ID Sendcloud (pour la création du parcel)
     *   4. Retourne les options triées par prix
     *
     * ENRICHISSEMENT SENDCLOUD :
     *   Chaque option reçoit sendcloud_shipping_method_id qui sera stocké sur l'Order.
     *   Ce champ est utilisé par SendcloudShipmentService après paiement confirmé
     *   pour créer le parcel et générer le bordereau PDF.
     *
     * @return JsonResponse {
     *   options: ShippingOption[],
     *   total_weight_grams: int,
     *   free_shipping_from: float
     * }
     */
    public function shippingOptions(Request $request): JsonResponse
    {
        $request->validate([
            'country'     => ['required', 'string', 'size:2'],
            'subtotal'    => ['required', 'numeric', 'min:0'],
            'postal_code' => ['nullable', 'string', 'max:10'],
        ]);

        $cart      = $this->cartService->getOrCreateCart($request->user());
        $cartItems = $cart->items()->get();

        // Panier vide côté serveur → on ne peut pas calculer le poids
        if ($cartItems->isEmpty()) {
            return response()->json([
                'message'            => 'Votre panier est vide.',
                'options'            => [],
                'total_weight_grams' => 0,
                'free_shipping_from' => ShippingService::FREE_SHIPPING_THRESHOLD_CENTS / 100,
            ], 422);
        }

        $shipping = new ShippingService();

        // Calcule le poids total en grammes (basé sur products.weight_grams × quantity)
        $totalWeight = $shipping->calculateCartWeight($cartItems);

        // Convertit le sous-total euros → centimes pour la comparaison avec le seuil
        $subtotalCents = (int) round($request->subtotal * 100);

        // Récupère les options depuis Sendcloud (avec fallback si API indisponible)
        $options = $shipping->getOptions(
            $totalWeight,
            $subtotalCents,
            strtoupper($request->country),
            $request->postal_code ?? '75000'
        );

        // ── Enrichissement Sendcloud ──────────────────────────────────────────
        // On fait correspondre chaque option (colissimo, chronopost...)
        // avec l'ID numérique Sendcloud de la méthode d'expédition.
        // Cet ID est indispensable pour créer le parcel après paiement.
        //
        // On utilise un try/catch car l'API Sendcloud peut être indisponible
        // sans bloquer le checkout (on retourne options sans ID dans ce cas).
        $sendcloudMethods = collect();

        try {
            $sendcloudData    = app(SendcloudService::class)->listShippingMethods();
            $sendcloudMethods = collect($sendcloudData['shipping_methods'] ?? []);
        } catch (\Throwable $e) {
            // L'API Sendcloud est optionnelle pour l'affichage des options
            // → on log un warning mais on ne plante pas le checkout
            Log::warning('[CartController] Impossible de récupérer les méthodes Sendcloud', [
                'message' => $e->getMessage(),
            ]);
        }

        // Enrichit chaque option avec l'ID Sendcloud correspondant
        $enrichedOptions = collect($options)->map(function (array $option) use ($sendcloudMethods): array {
            // On cherche la méthode Sendcloud dont le carrier ou le nom
            // contient le code de notre option (ex: "colissimo" dans "colissimo" ou "Colissimo Home")
            $carrierKey = strtolower($option['carrier'] ?? $option['key'] ?? '');

            $matched = $sendcloudMethods->first(function (array $method) use ($carrierKey): bool {
                $carrier = strtolower($method['carrier'] ?? '');
                $name    = strtolower($method['name'] ?? '');

                return str_contains($carrier, $carrierKey) || str_contains($name, $carrierKey);
            });

            // Ajoute les champs Sendcloud à l'option
            $option['sendcloud_shipping_method_id'] = $matched['id'] ?? null;
            $option['sendcloud_checkout_option_id'] = null; // Réservé pour Sendcloud v3
            $option['requires_service_point']       = false; // Pas de point relais par défaut

            return $option;
        })->values()->toArray(); // values() réindexe le tableau, toArray() le convertit

        return response()->json([
            'options'            => $enrichedOptions,
            'total_weight_grams' => $totalWeight,
            'free_shipping_from' => ShippingService::FREE_SHIPPING_THRESHOLD_CENTS / 100,
        ]);
    }


    // =========================================================================
    // CHECKOUT — FORMULAIRE CLASSIQUE
    // =========================================================================

    /**
     * Crée une commande et une session Stripe Checkout.
     *
     * ENDPOINT : POST /client/cart/checkout
     * UTILISÉ PAR : Page checkout formulaire (/checkout)
     *
     * DIFFÉRENCE AVEC applePayIntent() :
     *   checkout()       → Stripe Checkout Session (redirect vers page Stripe hébergée)
     *   applePayIntent() → Stripe PaymentIntent    (popup native Apple Pay sur la page)
     *
     * FLUX :
     *   1. Valide les données du formulaire
     *   2. Crée l'Order en BDD dans une transaction (rollback auto si erreur)
     *   3. Crée les OrderLines pour chaque article
     *   4. Crée la Stripe Checkout Session avec les line_items
     *   5. Stocke l'ID session Stripe sur l'Order
     *   6. Retourne l'URL de la session Stripe → frontend redirige
     *
     * WEBHOOK :
     *   Après paiement Stripe → webhook checkout.session.completed
     *   → StripeWebhookController::handle() → met à jour l'Order + crée parcel Sendcloud
     *
     * @return JsonResponse { success: true, checkout_url: string } HTTP 201
     */
    public function checkout(Request $request): JsonResponse
    {
        // ── Validation du formulaire ──────────────────────────────────────────
        // Les champs shipping_* sont obligatoires car nécessaires pour la livraison.
        // Les champs billing_* sont optionnels → fallback sur les champs shipping_*.
        // Les champs sendcloud_* sont optionnels → stockés pour la création du parcel.
        $validated = $request->validate([
            // Adresse de livraison (obligatoire)
            'shipping_name'    => ['required', 'string', 'max:255'],
            'shipping_email'   => ['required', 'email', 'max:255'],
            'shipping_phone'   => ['nullable', 'string', 'max:30'],
            'shipping_address' => ['required', 'string', 'max:255'],
            'shipping_city'    => ['required', 'string', 'max:120'],
            'shipping_zip'     => ['required', 'string', 'max:50'],
            'shipping_country' => ['required', 'string', 'max:120'],

            // Méthode de livraison choisie (obligatoire)
            'shipping_method_key'         => ['required', 'string', 'max:255'],
            'shipping_method_label'       => ['required', 'string', 'max:255'],
            'shipping_method_price_cents' => ['required', 'integer', 'min:0'],

            // Champs Sendcloud pour la création du parcel post-paiement (optionnels)
            'sendcloud_checkout_option_id' => ['nullable', 'string', 'max:255'],
            'sendcloud_shipping_method_id' => ['nullable', 'integer'],
            'sendcloud_service_point_id'   => ['nullable', 'string', 'max:255'],

            // Adresse de facturation (optionnelle → fallback shipping)
            'billing_name'       => ['nullable', 'string', 'max:255'],
            'billing_email'      => ['nullable', 'email', 'max:255'],
            'billing_vat_number' => ['nullable', 'string', 'max:100'],
            'billing_address'    => ['nullable', 'string', 'max:255'],
            'billing_line2'      => ['nullable', 'string', 'max:255'],
            'billing_zip'        => ['nullable', 'string', 'max:50'],
            'billing_city'       => ['nullable', 'string', 'max:120'],
            'billing_country'    => ['nullable', 'string', 'max:120'],

            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $client  = $request->user();
        $company = $client->company;

        // Un client B2B doit avoir une société associée
        if (! $company) {
            return response()->json([
                'message' => 'Aucune société associée à ce compte. Veuillez compléter votre profil.',
            ], 422);
        }

        // Récupère et valide le panier (vérifie les stocks, les prix, etc.)
        $cart      = $this->cartService->getOrCreateCart($client);
        $cart      = $this->cartService->validateCheckoutCart($cart, $client);
        $cartItems = $cart->items;

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Votre panier est vide.'], 422);
        }

        // ── Calcul des montants ───────────────────────────────────────────────
        // Les prix en BDD sont HT → on travaille en centimes pour éviter les
        // erreurs d'arrondi sur les flottants.
        $orderNumber   = 'NP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        $subtotalCents = (int) $cartItems->sum(fn ($item) => $item->price_cents * $item->quantity);
        $shippingCents = (int) $validated['shipping_method_price_cents'];
        $totalCents    = $subtotalCents + $shippingCents;

        // ── Création Order + OrderLines dans une transaction ─────────────────
        // DB::transaction() garantit l'atomicité :
        // Si la création des OrderLines échoue → l'Order est aussi annulé (rollback).
        // Évite d'avoir des Orders sans lignes en BDD.
        $order = DB::transaction(function () use (
            $client,
            $company,
            $cartItems,
            $validated,
            $orderNumber,
            $subtotalCents,
            $shippingCents,
            $totalCents
        ) {
            $order = Order::create([
                // Identité commande
                'number'   => $orderNumber,
                'status'   => 'pending_payment',
                'company_id'  => $company->id,
                'customer_id' => $client->id,

                // Contact client
                'customer_email'   => $validated['shipping_email'],
                'customer_name'    => $validated['shipping_name'],
                'customer_phone'   => $validated['shipping_phone'] ?? null,
                'customer_company' => $company->name ?? null,

                // Adresse de livraison
                'shipping_address' => $validated['shipping_address'],
                'shipping_city'    => $validated['shipping_city'],
                'shipping_zip'     => $validated['shipping_zip'],
                'shipping_country' => $validated['shipping_country'],

                // Méthode de livraison
                'shipping_method_key'   => $validated['shipping_method_key'],
                'shipping_method_label' => $validated['shipping_method_label'],
                'shipping_carrier'      => $validated['shipping_method_key'],
                'shipping_status'       => 'pending',

                // Champs Sendcloud — utilisés après paiement pour créer le parcel
                'sendcloud_checkout_option_id' => $validated['sendcloud_checkout_option_id'] ?? null,
                'sendcloud_shipping_method_id' => $validated['sendcloud_shipping_method_id'] ?? null,
                'sendcloud_service_point_id'   => $validated['sendcloud_service_point_id']   ?? null,

                // Adresse de facturation — fallback sur shipping si non renseigné
                'billing_name'       => $validated['billing_name']       ?? $validated['shipping_name'],
                'billing_email'      => $validated['billing_email']      ?? $validated['shipping_email'],
                'billing_vat_number' => $validated['billing_vat_number'] ?? $company->vat_number,
                'billing_address'    => $validated['billing_address']    ?? $validated['shipping_address'],
                'billing_line2'      => $validated['billing_line2']      ?? null,
                'billing_zip'        => $validated['billing_zip']        ?? $validated['shipping_zip'],
                'billing_city'       => $validated['billing_city']       ?? $validated['shipping_city'],
                'billing_country'    => $validated['billing_country']    ?? $validated['shipping_country'],

                // Montants en centimes
                'currency'       => 'EUR',
                'subtotal_cents' => $subtotalCents,
                'shipping_cents' => $shippingCents,
                'total_cents'    => $totalCents,
                'notes'          => $validated['notes'] ?? null,
            ]);

            // Crée une OrderLine par article du panier
            // On snapshot le produit (nom, SKU, image, prix) au moment de la commande
            // → si le produit change après, la commande garde les données d'origine
            foreach ($cartItems as $cartItem) {
                // On recharge le produit pour avoir les données fraîches (poids, etc.)
                $product = Product::find($cartItem->product_id);

                OrderLine::create([
                    'order_id'         => $order->id,
                    'product_id'       => $product?->id,
                    'name_snapshot'    => $cartItem->name,
                    'sku_snapshot'     => $cartItem->sku,
                    'model_snapshot'   => $cartItem->name,
                    'color_snapshot'   => $cartItem->color,
                    'size_snapshot'    => $cartItem->size,
                    'image_snapshot'   => $cartItem->image,
                    'unit_price_cents' => (int) $cartItem->price_cents,
                    'qty'              => (int) $cartItem->quantity,
                    'line_total_cents' => (int) $cartItem->price_cents * (int) $cartItem->quantity,
                ]);
            }

            return $order;
        });

        // ── Création de la Stripe Checkout Session ────────────────────────────
        // La session Stripe héberge la page de paiement sécurisée.
        // Le client est redirigé vers session->url pour saisir sa carte.
        // Après paiement → Stripe appelle notre webhook (checkout.session.completed).
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        // Construit les line_items Stripe depuis les articles du panier
        $lineItems = $this->cartService->buildStripeLineItems($cartItems);

        // Ajoute la livraison comme ligne séparée si elle est payante
        if ($shippingCents > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency'     => 'eur',
                    'product_data' => [
                        'name'        => 'Livraison',
                        'description' => $validated['shipping_method_label'],
                    ],
                    'unit_amount' => $shippingCents,
                ],
                'quantity' => 1,
            ];
        }

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'mode'                 => 'payment',
            'line_items'           => $lineItems,
            'customer_email'       => $validated['shipping_email'],

            // La session expire après 30 minutes d'inactivité
            'expires_at' => time() + (30 * 60),

            // Métadonnées stockées sur la session → récupérées dans le webhook
            'metadata' => [
                'order_id'     => (string) $order->id,
                'order_number' => $order->number,
            ],

            // Métadonnées sur le PaymentIntent (accessible via Stripe Dashboard)
            'payment_intent_data' => [
                'metadata' => [
                    'order_id'     => (string) $order->id,
                    'order_number' => $order->number,
                ],
            ],

            // URLs de retour après paiement
            // success_url → page de confirmation avec le numéro de commande
            // cancel_url  → page d'annulation (panier conservé)
            'success_url' => config('app.frontend_url') . '/checkout/success?order=' . $order->number,
            'cancel_url'  => config('app.frontend_url') . '/checkout/cancel?order='  . $order->number,
        ]);

        // Stocke l'ID session Stripe sur l'Order pour le retrouver dans le webhook
        $order->update(['stripe_checkout_session_id' => $session->id]);

        Log::info('[CartController] Checkout session créée', [
            'order_id'    => $order->id,
            'order_number' => $order->number,
            'total_cents' => $totalCents,
            'session_id'  => $session->id,
        ]);

        return response()->json([
            'success'      => true,
            'checkout_url' => $session->url,
        ], 201);
    }


    // =========================================================================
    // APPLE PAY / GOOGLE PAY — EXPRESS CHECKOUT
    // =========================================================================

    /**
     * Crée un PaymentIntent Stripe pour Apple Pay / Google Pay.
     *
     * ENDPOINT : POST /client/cart/apple-pay-intent
     * UTILISÉ PAR : Composant ApplePayButton.svelte
     *
     * DIFFÉRENCE FONDAMENTALE AVEC checkout() :
     * ┌─────────────────────────────────────────────────────────────────┐
     * │ checkout()       → Stripe Checkout SESSION                      │
     * │   Le client est redirigé vers une page Stripe hébergée.        │
     * │   Stripe gère l'UI complète du paiement.                        │
     * │                                                                 │
     * │ applePayIntent() → Stripe PAYMENT INTENT                        │
     * │   Le paiement se fait dans une popup native Apple Pay           │
     * │   SANS quitter la page courante.                                │
     * │   L'UI est gérée par iOS/macOS, pas par Stripe.                 │
     * └─────────────────────────────────────────────────────────────────┘
     *
     * FLUX COMPLET :
     *   1. Client clique "Payer avec Apple Pay" sur la page panier
     *   2. ApplePayButton.svelte appelle cet endpoint → reçoit client_secret
     *   3. Popup Apple Pay s'ouvre (gérée par iOS/macOS)
     *   4. Client choisit sa carte + confirme avec Face ID / Touch ID
     *   5. ApplePayButton.svelte appelle stripe.confirmCardPayment(client_secret)
     *   6. Stripe confirme → webhook payment_intent.succeeded déclenché
     *   7. Notre webhook met à jour l'Order (statut, adresse Apple Pay, etc.)
     *
     * POURQUOI créer l'Order AVANT le paiement ?
     *   Le PaymentIntent Stripe a besoin d'un order_id dans ses métadonnées
     *   pour que le webhook puisse retrouver et mettre à jour la commande.
     *   Si le paiement échoue → l'Order reste en 'pending_payment' indéfiniment.
     *   Un job de nettoyage (à créer) peut purger ces orders orphelins.
     *
     * NOTE SUR LA LIVRAISON :
     *   Le montant livraison n'est pas connu à cette étape.
     *   Apple Pay gère la sélection du transporteur dans sa popup.
     *   Le composant frontend met à jour le montant via l'event 'shippingoptionchange'.
     *   Après paiement, le webhook met à jour shipping_cents sur l'Order.
     *
     * @return JsonResponse {
     *   client_secret: string,  // Clé secrète du PaymentIntent → utilisée par stripe.confirmCardPayment()
     *   order_id:      int,     // ID de l'Order créé en BDD
     *   total_cents:   int      // Montant TTC en centimes (hors livraison à ce stade)
     * }
     */
    public function applePayIntent(Request $request): JsonResponse
    {
        $user = $request->user();

        // Vérifie que le client a bien une société (B2B only)
        if (! $user->company_id) {
            return response()->json([
                'message' => 'Aucune société associée à ce compte.',
            ], 422);
        }

        $cart  = $this->cartService->getOrCreateCart($user);
        $items = $cart->items()->with('product')->get();

        if ($items->isEmpty()) {
            return response()->json(['message' => 'Panier vide.'], 422);
        }

        // ── Calcul des montants ───────────────────────────────────────────────
        // Les prix en BDD sont HT → on applique la TVA 20% pour le total TTC.
        // La livraison sera ajoutée par le composant frontend (Apple Pay la gère
        // via l'event shippingoptionchange).
        $subtotalCents = (int) $items->sum(fn ($item) => $item->price_cents * $item->quantity);
        $tvaCents      = (int) round($subtotalCents * 0.20);
        $totalCents    = $subtotalCents + $tvaCents;
        // Note : shipping_cents sera mis à 0 pour l'instant et mis à jour par le webhook

        // ── Création de l'Order en brouillon ─────────────────────────────────
        // Statut 'pending_payment' → sera mis à 'paid' par le webhook.
        // Les champs shipping_* (adresse, méthode) seront remplis
        // après paiement depuis les données Apple Pay reçues dans le webhook.
        $order = DB::transaction(function () use ($user, $items, $subtotalCents, $tvaCents, $totalCents) {
            $order = Order::create([
                'number'         => 'NP-' . date('Ymd') . '-' . strtoupper(Str::random(6)),
                'status'         => 'pending_payment',
                'customer_id'    => $user->id,
                'company_id'     => $user->company_id,
                'customer_email' => $user->email,
                'customer_name'  => $user->name,
                'currency'       => 'EUR',
                'subtotal_cents' => $subtotalCents,
                'shipping_cents' => 0,          // mis à jour après Apple Pay via webhook
                'total_cents'    => $totalCents, // TTC articles, livraison ajoutée après
                'shipping_status' => 'pending',
            ]);

            // Crée les lignes de commande avec snapshot des articles
            foreach ($items as $item) {
                $order->lines()->create([
                    'product_id'       => $item->product_id,
                    'name_snapshot'    => $item->name,
                    'sku_snapshot'     => $item->sku,
                    'model_snapshot'   => $item->name,
                    'color_snapshot'   => $item->color   ?? null,
                    'size_snapshot'    => $item->size    ?? null,
                    'image_snapshot'   => $item->image   ?? null,
                    'unit_price_cents' => (int) $item->price_cents,
                    'qty'              => (int) $item->quantity,
                    'line_total_cents' => (int) $item->price_cents * (int) $item->quantity,
                ]);
            }

            return $order;
        });

        // ── Création du Stripe PaymentIntent ─────────────────────────────────
        // Le PaymentIntent est différent d'une Checkout Session :
        //   → Il ne crée pas de page de paiement hébergée
        //   → Il fournit un client_secret que le SDK Stripe JS utilise
        //      pour confirmer le paiement depuis Apple Pay (côté navigateur)
        //
        // CAPTURE_METHOD 'automatic' :
        //   Le montant est capturé dès la confirmation du paiement.
        //   Alternative : 'manual' = pré-autorisation, capture séparée.
        //   Pour Ness Paris → 'automatic' est le bon choix (paiement B2B direct).
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount'               => $totalCents,
            'currency'             => 'eur',
            'capture_method'       => 'automatic',

            // 'card' supporte Apple Pay et Google Pay via le Payment Request API
            'payment_method_types' => ['card'],

            // Métadonnées → récupérées dans le webhook payment_intent.succeeded
            // pour retrouver et mettre à jour l'Order en BDD
            'metadata' => [
                'order_id'   => (string) $order->id,
                'user_id'    => (string) $user->id,
                'company_id' => (string) ($user->company_id ?? ''),
            ],

            // Description visible dans le dashboard Stripe
            'description' => "Commande {$order->number} — Ness Paris",
        ]);

        Log::info('[CartController] Apple Pay intent créé', [
            'order_id'          => $order->id,
            'order_number'      => $order->number,
            'total_cents'       => $totalCents,
            'payment_intent_id' => $paymentIntent->id,
        ]);

        return response()->json([
            // client_secret → passé à stripe.confirmCardPayment() côté frontend
            // NE JAMAIS logger en production (contient des données sensibles)
            'client_secret' => $paymentIntent->client_secret,
            'order_id'      => $order->id,
            'total_cents'   => $totalCents,
        ]);
    }
}
