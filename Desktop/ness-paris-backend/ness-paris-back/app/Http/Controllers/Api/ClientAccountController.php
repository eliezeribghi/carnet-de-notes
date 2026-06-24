<?php
// =============================================================================
// app/Http/Controllers/Api/ClientAccountController.php
//
// RESPONSABILITÉ :
//   Toutes les opérations du compte client B2B (profil, company, commandes,
//   factures, dashboard).
//
// ARCHITECTURE DONNÉES :
//   Laravel BDD → auth (email/password), commandes, panier, pennylane_*_id
//   Pennylane   → données pro client (nom, SIRET, TVA, adresse, factures)
//
// RÈGLES :
//   - invoices() → GET depuis Pennylane (pas la table Invoice locale)
//   - updateCompany() → sync Pennylane après mise à jour BDD
//   - me() → enrichit avec données Pennylane si pennylane_customer_id présent
//   - La table Invoice locale est conservée en stub (migration progressive)
//     mais n'est plus la source de vérité pour les factures client
// =============================================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\VerifyCompanyJob;
use App\Models\Company;
use App\Models\Order;
use App\Services\PennylaneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClientAccountController extends Controller
{
    // =========================================================================
    // PROFIL CLIENT
    // =========================================================================

    /**
     * Retourne les données du client connecté.
     *
     * ENRICHISSEMENT PENNYLANE :
     *   Si le client a un pennylane_customer_id, on récupère ses données pro
     *   depuis Pennylane (nom, adresse, TVA) et on les fusionne dans la réponse.
     *   Les données Pennylane ont priorité sur la table companies pour les
     *   champs pro (nom, TVA, adresse facturation).
     *
     *   Si Pennylane est désactivé (PENNYLANE_ENABLED=false) ou si le client
     *   n'a pas encore de pennylane_customer_id → fallback sur la table companies.
     */
    public function me(Request $request): JsonResponse
    {
        $user    = $request->user()->load('company');
        $company = $user->company;

        // Données de base depuis la BDD locale
        $companyData = $company ? $company->toArray() : null;

        // Enrichissement depuis Pennylane si disponible
        if ($user->pennylane_customer_id && $companyData) {
            $pennylaneData = app(PennylaneService::class)
                ->getCustomer($user->pennylane_customer_id);

            if ($pennylaneData) {
                // Les données Pennylane écrasent les champs pro de la BDD locale
                // La BDD locale garde le contrôle sur status, is_active, company_id
                $companyData = array_merge($companyData, [
                    'name'            => $pennylaneData['name']        ?? $companyData['name'],
                    'vat_number'      => $pennylaneData['vat_number']  ?? $companyData['vat_number'],
                    'email'           => $pennylaneData['emails'][0]   ?? $companyData['email'],
                    'phone'           => $pennylaneData['phone']       ?? $companyData['phone'],
                    'billing_address' => $pennylaneData['billing_address']['address']     ?? $companyData['billing_address'] ?? null,
                    'billing_zip'     => $pennylaneData['billing_address']['postal_code'] ?? $companyData['billing_zip']     ?? null,
                    'billing_city'    => $pennylaneData['billing_address']['city']        ?? $companyData['billing_city']    ?? null,
                    'billing_country' => $pennylaneData['billing_address']['country_alpha2'] ?? $companyData['billing_country'] ?? 'FR',
                    '_source'         => 'pennylane', // flag debug — à retirer en prod
                ]);
            }
        }

        return response()->json([
            'id'                    => $user->id,
            'name'                  => $user->name,
            'email'                 => $user->email,
            'role'                  => $user->role,
            'is_active'             => $user->is_active,
            'pennylane_customer_id' => $user->pennylane_customer_id,
            'company'               => $companyData,
        ]);
    }


    // =========================================================================
    // DASHBOARD
    // =========================================================================

    /**
     * Retourne les statistiques et données récentes du dashboard client.
     *
     * NOTE invoices :
     *   On compte les commandes avec pennylane_invoice_id (source de vérité)
     *   plutôt que la table Invoice locale qui sera dépréciée.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user      = $request->user();
        $companyId = $user->company_id;

        if (!$companyId) {
            return response()->json([
                'company'         => null,
                'stats'           => [],
                'recent_orders'   => [],
                'recent_invoices' => [],
            ]);
        }

        $recentOrders = Order::where('company_id', $companyId)
            ->with('lines')
            ->latest()
            ->take(5)
            ->get();

        // Factures = commandes payées avec une facture Pennylane générée
        $recentInvoices = Order::where('company_id', $companyId)
            ->whereNotNull('pennylane_invoice_id')
            ->where('status', 'paid')
            ->latest('paid_at')
            ->take(5)
            ->get(['id', 'number', 'total_cents', 'paid_at', 'pennylane_invoice_id']);

        return response()->json([
            'company' => $user->company,
            'stats'   => [
                'orders_in_progress' => Order::where('company_id', $companyId)
                    ->whereIn('status', ['processing', 'paid', 'pending_payment'])
                    ->count(),
                'invoices_available' => Order::where('company_id', $companyId)
                    ->whereNotNull('pennylane_invoice_id')
                    ->count(),
                'last_order_at' => Order::where('company_id', $companyId)
                    ->latest()
                    ->value('created_at'),
            ],
            'recent_orders'   => $recentOrders,
            'recent_invoices' => $recentInvoices,
        ]);
    }


    // =========================================================================
    // COMMANDES
    // =========================================================================

    /**
     * Liste toutes les commandes du client connecté.
     */
    public function orders(Request $request): JsonResponse
    {
        $orders = Order::where('company_id', $request->user()->company_id)
            ->with('lines')
            ->latest()
            ->get();

        return response()->json(['data' => $orders]);
    }

    /**
     * Détail d'une commande.
     * Vérifie que la commande appartient bien à la company du client connecté.
     */
    public function showOrder(Request $request, Order $order): JsonResponse
    {
        if ($order->company_id !== $request->user()->company_id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($order->load('lines'));
    }

    /**
     * Met à jour les informations de facturation d'une commande.
     * Utilisé quand le client corrige son adresse de facture après commande.
     */
    public function updateOrderInvoice(Request $request, int $orderId): JsonResponse
    {
        $validated = $request->validate([
            'billing_name'    => ['required', 'string', 'max:255'],
            'company_email'   => ['required', 'email', 'max:255'],
            'vat_number'      => ['nullable', 'string', 'max:100'],
            'billing_line1'   => ['required', 'string', 'max:255'],
            'billing_line2'   => ['nullable', 'string', 'max:255'],
            'billing_zip'     => ['required', 'string', 'max:50'],
            'billing_city'    => ['required', 'string', 'max:120'],
            'billing_country' => ['required', 'string', 'max:120'],
        ]);

        $order = Order::where('id', $orderId)
            ->where('customer_id', $request->user()->id)
            ->firstOrFail();

        $order->update([
            'billing_name'       => $validated['billing_name'],
            'billing_email'      => $validated['company_email'],
            'billing_vat_number' => $validated['vat_number'] ?? null,
            'billing_address'    => $validated['billing_line1'],
            'billing_line2'      => $validated['billing_line2'] ?? null,
            'billing_zip'        => $validated['billing_zip'],
            'billing_city'       => $validated['billing_city'],
            'billing_country'    => $validated['billing_country'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Informations de facturation mises à jour.',
            'order'   => $order->only([
                'id', 'billing_name', 'billing_email', 'billing_vat_number',
                'billing_address', 'billing_line2', 'billing_zip',
                'billing_city', 'billing_country',
            ]),
        ]);
    }


    // =========================================================================
    // FACTURES — SOURCE DE VÉRITÉ : PENNYLANE
    // =========================================================================

    /**
     * Liste les factures du client depuis Pennylane.
     *
     * ARCHITECTURE :
     *   Les factures sont stockées dans Pennylane (pas en BDD locale).
     *   On retrouve les commandes avec un pennylane_invoice_id et on
     *   récupère les détails facture depuis Pennylane via getCustomerInvoices().
     *
     *   Fallback : si Pennylane est désactivé ou indisponible, on retourne
     *   les commandes payées avec leurs données de base.
     */
    public function invoices(Request $request): JsonResponse
    {
        $user = $request->user();

        // Commandes payées avec facture Pennylane générée
        $paidOrders = Order::where('company_id', $user->company_id)
            ->whereNotNull('pennylane_invoice_id')
            ->where('status', 'paid')
            ->latest('paid_at')
            ->get(['id', 'number', 'total_cents', 'paid_at', 'pennylane_invoice_id',
                   'shipping_method_label', 'subtotal_cents', 'shipping_cents']);

        // Enrichissement Pennylane si disponible
        // On retourne toujours les données de base même si Pennylane est down
        $invoices = $paidOrders->map(function (Order $order) use ($user) {
            $invoiceData = [
                'id'                  => $order->id,
                'order_number'        => $order->number,
                'total_cents'         => $order->total_cents,
                'subtotal_cents'      => $order->subtotal_cents,
                'shipping_cents'      => $order->shipping_cents,
                'paid_at'             => $order->paid_at,
                'pennylane_invoice_id'=> $order->pennylane_invoice_id,
                'pdf_url'             => null, // rempli ci-dessous si Pennylane dispo
            ];

            // TODO : ajouter PennylaneService::getInvoicePdfUrl() quand l'endpoint
            // est confirmé dans la doc Pennylane V2. Pour l'instant on retourne
            // les données BDD avec l'ID Pennylane que le frontend peut utiliser.

            return $invoiceData;
        });

        return response()->json(['data' => $invoices]);
    }

    /**
     * Détail d'une facture.
     * Retourne les données de la commande + l'ID Pennylane pour le PDF.
     */
    public function showInvoice(Request $request, int $invoiceId): JsonResponse
    {
        $order = Order::where('id', $invoiceId)
            ->where('company_id', $request->user()->company_id)
            ->whereNotNull('pennylane_invoice_id')
            ->firstOrFail();

        return response()->json($order->load('lines'));
    }


    // =========================================================================
    // COMPANY
    // =========================================================================

    /**
     * Retourne les données de la company du client.
     * Enrichit depuis Pennylane si disponible.
     */
    public function company(Request $request): JsonResponse
    {
        // On réutilise me() qui gère déjà l'enrichissement Pennylane
        // et retourne company dans sa réponse
        $user    = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json(null);
        }

        $data = $company->toArray();

        if ($user->pennylane_customer_id) {
            $pennylaneData = app(PennylaneService::class)
                ->getCustomer($user->pennylane_customer_id);

            if ($pennylaneData) {
                $data = array_merge($data, [
                    'name'            => $pennylaneData['name']        ?? $data['name'],
                    'vat_number'      => $pennylaneData['vat_number']  ?? $data['vat_number'],
                    'billing_address' => $pennylaneData['billing_address']['address']        ?? $data['billing_address'] ?? null,
                    'billing_zip'     => $pennylaneData['billing_address']['postal_code']    ?? $data['billing_zip']     ?? null,
                    'billing_city'    => $pennylaneData['billing_address']['city']           ?? $data['billing_city']    ?? null,
                    'billing_country' => $pennylaneData['billing_address']['country_alpha2'] ?? $data['billing_country'] ?? 'FR',
                ]);
            }
        }

        return response()->json($data);
    }

    /**
     * Crée une company pour un compte client qui n'en a pas encore.
     *
     * CAS D'USAGE :
     *   Client créé sans company (edge case) ou client qui complète son profil.
     *
     * FLUX :
     *   1. Valide les données
     *   2. Crée la company en BDD (status: pending_review)
     *   3. Lie le user à cette company
     *   4. Lance VerifyCompanyJob (SIREN/VIES en arrière-plan)
     */
    public function createCompany(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->company_id) {
            return response()->json(['message' => 'Une société est déjà associée à ce compte.'], 422);
        }

        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'legal_name'    => ['nullable', 'string', 'max:255'],
            'email'         => ['nullable', 'email', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:30'],
            'vat_number'    => ['nullable', 'string', 'max:100'],
            'siren'         => ['nullable', 'string', 'max:20'],
            'siret'         => ['nullable', 'string', 'max:20'],
            'country'       => ['nullable', 'string', 'size:2'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'postal_code'   => ['nullable', 'string', 'max:10'],
            'city'          => ['nullable', 'string', 'max:120'],
        ]);

        $company = Company::create(array_merge($data, [
            'status'    => Company::STATUS_PENDING_REVIEW,
            'is_active' => true,
            'country'   => $data['country'] ?? 'FR',
        ]));

        $user->update(['company_id' => $company->id]);

        // Vérification SIREN/VIES en arrière-plan
        VerifyCompanyJob::dispatch($company->id);

        return response()->json([
            'message' => 'Société créée. Vérification automatique en cours.',
            'company' => $company,
        ], 201);
    }

    /**
     * Met à jour les données de la company du client.
     *
     * COMPORTEMENTS :
     *   1. Si SIREN, SIRET ou TVA change → repasse en pending_review + relance VerifyCompanyJob
     *   2. Sync vers Pennylane si le client a un pennylane_customer_id
     *      (non bloquant — si Pennylane échoue, la BDD est quand même mise à jour)
     */
    public function updateCompany(Request $request): JsonResponse
    {
        $user    = $request->user();
        $company = $user->company;

        if (!$company) {
            return response()->json(['message' => 'Aucune société associée à ce compte.'], 404);
        }

        $data = $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'legal_name'       => ['nullable', 'string', 'max:255'],
            'email'            => ['nullable', 'email', 'max:255'],
            'phone'            => ['nullable', 'string', 'max:30'],
            'vat_number'       => ['nullable', 'string', 'max:100'],
            'siren'            => ['nullable', 'string', 'max:20'],
            'siret'            => ['nullable', 'string', 'max:20'],
            'address_line1'    => ['nullable', 'string', 'max:255'],
            'address_line2'    => ['nullable', 'string', 'max:255'],
            'postal_code'      => ['nullable', 'string', 'max:10'],
            'city'             => ['nullable', 'string', 'max:120'],
            'shipping_address' => ['nullable', 'string', 'max:255'],
            'shipping_city'    => ['nullable', 'string', 'max:120'],
            'shipping_zip'     => ['nullable', 'string', 'max:10'],
            'shipping_country' => ['nullable', 'string', 'max:2'],
            'billing_address'  => ['nullable', 'string', 'max:255'],
            'billing_city'     => ['nullable', 'string', 'max:120'],
            'billing_zip'      => ['nullable', 'string', 'max:10'],
            'billing_country'  => ['nullable', 'string', 'max:2'],
        ]);

        // ── Détecte si un champ légal a changé ───────────────────────────────
        // SIREN, SIRET ou TVA modifiés → re-vérification obligatoire
        $needsReverification = collect(['siren', 'siret', 'vat_number'])
            ->contains(fn ($field) =>
                isset($data[$field]) && $data[$field] !== $company->$field
            );

        if ($needsReverification) {
            $data['status'] = Company::STATUS_PENDING_REVIEW;
        }

        // ── Mise à jour BDD locale ────────────────────────────────────────────
        $company->update($data);

        // ── Re-vérification SIREN/VIES si nécessaire ─────────────────────────
        if ($needsReverification) {
            VerifyCompanyJob::dispatch($company->id);
        }

        // ── Sync vers Pennylane ───────────────────────────────────────────────
        // Non bloquant — si Pennylane est désactivé ou en erreur,
        // la mise à jour BDD est quand même confirmée au client.
        if ($user->pennylane_customer_id) {
            $pennylanePayload = array_filter([
                'name'        => $data['name']       ?? null,
                'phone'       => $data['phone']       ?? null,
                'vat_number'  => $data['vat_number']  ?? null,
                'billing_address' => array_filter([
                    'address'        => $data['billing_address'] ?? $data['address_line1'] ?? null,
                    'postal_code'    => $data['billing_zip']     ?? $data['postal_code']   ?? null,
                    'city'           => $data['billing_city']    ?? $data['city']          ?? null,
                    'country_alpha2' => $data['billing_country'] ?? $company->country      ?? 'FR',
                ], fn($v) => $v !== null && $v !== ''),
            ], fn($v) => $v !== null && $v !== '' && $v !== []);

            $synced = app(PennylaneService::class)
                ->updateCustomer($user->pennylane_customer_id, $pennylanePayload);

            if (!$synced) {
                Log::warning('[ClientAccountController] Sync Pennylane échouée après updateCompany', [
                    'user_id'      => $user->id,
                    'pennylane_id' => $user->pennylane_customer_id,
                ]);
            }
        }

        return response()->json([
            'message' => $needsReverification
                ? 'Société mise à jour. Re-vérification en cours.'
                : 'Informations de la société mises à jour.',
            'company' => $company->fresh(),
        ]);
    }


    // =========================================================================
    // LOOKUP SIRET — pré-remplissage formulaire inscription
    // =========================================================================

    /**
     * Recherche une entreprise par SIRET via l'API INSEE (Sirene).
     *
     * UTILISÉ PAR : formulaire d'inscription SvelteKit
     *   Le client saisit son SIRET → on appelle cet endpoint →
     *   le formulaire se pré-remplit automatiquement avec les données INSEE.
     *
     * ENDPOINT : GET /client/company/lookup?siret=XXXXXXXXXXXXXX
     *
     * RETOURNE :
     *   name, legal_name, address, postal_code, city, country, siren, siret
     *
     * RATE LIMIT : throttle:10,1 dans routes/api.php (à ajouter)
     */
    public function lookupSiret(Request $request): JsonResponse
    {
        $request->validate([
            'siret' => ['required', 'string', 'size:14'],
        ]);

        $siret   = preg_replace('/\D/', '', $request->siret);
        $service = app(\App\Services\Verification\SireneVerificationService::class);
        $result  = $service->verifySiret($siret);

        if ($result['valid'] === false) {
            return response()->json([
                'found'   => false,
                'message' => $result['message'],
            ], 404);
        }

        if ($result['valid'] === null) {
            // Erreur technique INSEE — on ne bloque pas, on laisse le client saisir manuellement
            return response()->json([
                'found'   => false,
                'message' => 'Service INSEE temporairement indisponible. Saisissez vos informations manuellement.',
            ], 503);
        }

        // SIRET valide → retourne les données pour pré-remplir le formulaire
        $meta = $result['meta'];

        return response()->json([
            'found'       => true,
            'siret'       => $siret,
            'siren'       => $meta['siren']    ?? substr($siret, 0, 9),
            'name'        => $meta['denomination'] ?? null,
            'legal_name'  => $meta['denomination'] ?? null,
            'address'     => $meta['address']      ?? null,
            'postal_code' => $meta['postal_code']  ?? null,
            'city'        => $meta['city']         ?? null,
            'country'     => 'FR',
        ]);
    }
}