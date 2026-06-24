<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * SyncProductsToMedusa
 *
 * Commande Artisan qui synchronise les product_groups publiés de MySQL (Laravel)
 * vers Medusa.js (moteur e-commerce). Laravel est la source de vérité — Medusa
 * reçoit une copie des produits pour gérer le checkout et les paiements Stripe.
 *
 * Usage :
 *   php artisan medusa:sync-products
 *
 * Prérequis :
 *   - Medusa doit être démarré sur le port 9000
 *   - MEDUSA_BACKEND_URL et MEDUSA_API_TOKEN doivent être définis dans .env
 *
 * Stratégie :
 *   1. Créer le produit SANS variants (évite les timeouts sur gros catalogues)
 *   2. Ajouter les variants par batch de 10 via /variants/batch
 *
 * @author Eliezer Ibghi
 */
class SyncProductsToMedusa extends Command
{
    protected $signature   = 'medusa:sync-products';
    protected $description = 'Synchronise les product_groups + variants + images de Laravel vers Medusa';

    // ── Configuration injectée depuis .env ──────────────────────────────────
    private string $baseUrl;    // ex: http://localhost:9000
    private string $apiKey;     // Token admin Medusa
    private string $imageBase;  // Base URL publique pour les images Laravel (storage)

    // ── Statistiques de fin de sync ──────────────────────────────────────────
    private int $created  = 0;
    private int $skipped  = 0;
    private int $failed   = 0;

    public function __construct()
    {
        parent::__construct();
    }

    // ────────────────────────────────────────────────────────────────────────
    //  ENTRY POINT
    // ────────────────────────────────────────────────────────────────────────

    public function handle(): void
    {
        // Initialisation des variables d'environnement
        $this->baseUrl   = env('MEDUSA_BACKEND_URL', 'http://localhost:9000');
        $this->apiKey    = env('MEDUSA_API_TOKEN', '');
        $this->imageBase = rtrim(env('APP_URL', 'http://localhost:8001'), '/') . '/storage';

        // Vérification de la config minimale avant de démarrer
        if (empty($this->apiKey)) {
            $this->error('❌ MEDUSA_API_TOKEN manquant dans le .env — sync annulée');
            return;
        }

        // Récupération de tous les groupes publiés
        $groups = DB::select("SELECT * FROM product_groups WHERE is_published = 1");

        $this->info('');
        $this->info('🚀 Démarrage de la sync Medusa');
        $this->info('📦 ' . count($groups) . ' groupes publiés trouvés');
        $this->info('');

        foreach ($groups as $group) {
            $this->processGroup($group);
        }

        // ── Rapport final ────────────────────────────────────────────────────
        $this->info('');
        $this->info('─────────────────────────────────────');
        $this->info("✅ Sync terminée");
        $this->info("   • Créés   : {$this->created}");
        $this->info("   • Ignorés : {$this->skipped}");
        $this->info("   • Échoués : {$this->failed}");
        $this->info('─────────────────────────────────────');
    }

    // ────────────────────────────────────────────────────────────────────────
    //  TRAITEMENT D'UN GROUPE
    // ────────────────────────────────────────────────────────────────────────

    private function processGroup(object $group): void
    {
        $this->info("▶ [{$group->id}] {$group->model_name}");

        // ── 1. Récupération des variants depuis MySQL ────────────────────────
        // On joint colors et sizes pour avoir les libellés lisibles.
        // On récupère aussi size_segment (KID/ADULT) et age_group pour Medusa.
        $variants = DB::select("
            SELECT
                p.*,
                c.display_name  AS color_name,
                s.label         AS size_label,
                s.segment       AS size_segment,
                s.sort_order    AS size_sort_order,
                p.age_group
            FROM products p
            JOIN colors c ON c.id = p.color_id
            JOIN sizes  s ON s.id = p.size_id
            WHERE p.product_group_id = ?
            ORDER BY s.sort_order ASC, c.display_name ASC
        ", [$group->id]);

        // Un groupe sans variants ne peut pas être vendu — on skip
        if (empty($variants)) {
            $this->warn("   ⚠️  Aucun variant — groupe ignoré");
            $this->skipped++;
            return;
        }

        // ── 2. Récupération des images publiques du groupe ───────────────────
        // On ne prend que les images de type 'flat', dédupliquées par path,
        // triées par position pour respecter l'ordre défini dans l'admin.
        $images = DB::select("
            SELECT pi.path
            FROM product_images pi
            JOIN products p ON p.id = pi.product_id
            WHERE p.product_group_id = ?
              AND pi.type = 'flat'
            GROUP BY pi.path
            ORDER BY MIN(pi.position) ASC
        ", [$group->id]);

        // Construction des URLs complètes accessibles publiquement
        $medusaImages = collect($images)
            ->filter(fn($img) => !empty($img->path))
            ->map(fn($img) => [
                'url' => rtrim($this->imageBase, '/') . '/' . ltrim($img->path, '/'),
            ])
            ->values()
            ->toArray();

        // ── 3. Construction des options Couleur / Taille ─────────────────────
        // Medusa a besoin de la liste de toutes les valeurs possibles
        // pour chaque option avant de pouvoir créer les variants.
        $colors = collect($variants)
            ->pluck('color_name')
            ->unique()
            ->values()
            ->toArray();

        $sizes = collect($variants)
            ->pluck('size_label')
            ->unique()
            ->values()
            ->toArray();

        // ── 4. Construction des variants Medusa ──────────────────────────────
        // Chaque variant correspond à une combinaison couleur + taille.
        // On injecte age_group et size_segment dans les metadata pour que
        // le storefront puisse filtrer les produits enfant / adulte.
        $medusaVariants = [];

        foreach ($variants as $v) {
            $medusaVariants[] = [
                // Titre affiché dans l'admin Medusa
                'title'   => $v->color_name . ' / ' . $v->size_label,

                // SKU = identifiant unique du variant (source de vérité MySQL)
                'sku'     => $v->sku,

                // Code-barres si disponible (CODE128 / EAN13)
                'barcode' => $v->barcode_value ?? null,

                // Prix en centimes (Medusa travaille toujours en centimes)
                'prices'  => [[
                    'currency_code' => 'eur',
                    'amount'        => (int) round((float) $v->price * 100),
                ]],

                // Options qui correspondent aux options déclarées sur le produit
                'options' => [
                    'Couleur' => $v->color_name,
                    'Taille'  => $v->size_label,
                ],

                // Metadata : données métier Laravel qui voyagent avec le variant
                // Utilisées pour les webhooks retour (Medusa → Laravel)
                'metadata' => [
                    'mysql_id'     => $v->id,           // ID produit MySQL
                    'mysql_sku'    => $v->sku,           // SKU pour retrouver le produit
                    'stock'        => $v->stock_quantity, // Stock au moment de la sync
                    'age_group'    => $v->age_group,      // 'adult' | 'kid' | 'both'
                    'size_segment' => $v->size_segment,   // 'ADULT' | 'KID' | 'UNISEX'
                ],
            ];
        }

        // ── 5. Payload produit (sans variants — ajoutés séparément) ──────────
        // On n'envoie PAS les variants ici pour éviter les timeouts Medusa
        // sur les groupes avec beaucoup de combinaisons couleur/taille.
        // Les variants sont ajoutés en batch juste après.
        $payload = [
            'title'       => $group->model_name,
            'subtitle'    => $group->subtitle    ?? null,
            'description' => $group->description ?? null,

            // Handle = slug unique Medusa — on suffixe '-ness' pour éviter
            // les collisions avec d'éventuels produits déjà présents dans Medusa
            'handle'      => $group->slug . '-ness',
            'status'      => 'published',

            // external_id permet de retrouver le produit Medusa depuis son ID MySQL
            'external_id' => (string) $group->id,

            'images'  => $medusaImages,
            'options' => [
                ['title' => 'Couleur', 'values' => $colors],
                ['title' => 'Taille',  'values' => $sizes],
            ],

            // Metadata groupe : informations métier au niveau du produit
            'metadata' => [
                'mysql_group_id' => $group->id,
                'composition'    => $group->composition ?? null,

                // age_group calculé dynamiquement à partir des variants du groupe
                // ex: groupe 100% enfant → 'kid', mixte → 'both'
                'age_group'      => $this->resolveGroupAgeGroup($variants),
            ],
        ];

        // ── 6. Création du produit dans Medusa ───────────────────────────────
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(120)->post("{$this->baseUrl}/admin/products", $payload);

        // Gestion des erreurs de création
        // Cas courant : handle déjà existant (produit déjà synced)
        // → ce n'est PAS bloquant, on skip proprement
        if (! $response->successful()) {
            $body = $response->body();

            if (str_contains($body, 'already exists')) {
                $this->warn("   ⏭  Déjà existant dans Medusa — ignoré");
                $this->skipped++;
            } else {
                $this->error("   ✖  Erreur création : {$body}");
                $this->failed++;
            }
            return;
        }

        // Récupération de l'ID Medusa du produit fraîchement créé
        $medusaId = $response->json('product.id');
        $this->info("   ✔  Produit créé → {$medusaId} (" . count($medusaImages) . " images)");

        // ── 7. Ajout des variants par batch de 10 ────────────────────────────
        // Medusa peut être lent sur les gros inserts — le batch de 10 évite
        // les timeouts et permet de relancer proprement en cas d'échec partiel.
        $batches    = array_chunk($medusaVariants, 10);
        $totalBatch = count($batches);

        foreach ($batches as $i => $batch) {
            $batchNum = $i + 1;

            $batchResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(120)->post(
                "{$this->baseUrl}/admin/products/{$medusaId}/variants/batch",
                ['create' => $batch]
            );

            if ($batchResponse->successful()) {
                $this->info("   ✔  Batch {$batchNum}/{$totalBatch} — " . count($batch) . " variants");
            } else {
                $this->error("   ✖  Batch {$batchNum}/{$totalBatch} échoué : " . $batchResponse->body());
            }
        }

        $this->created++;
        $this->info('');
    }

    // ────────────────────────────────────────────────────────────────────────
    //  HELPERS PRIVÉS
    // ────────────────────────────────────────────────────────────────────────

    /**
     * Détermine l'age_group dominant d'un groupe à partir de ses variants.
     *
     * Logique :
     *  - Tous les variants sont 'kid'   → retourne 'kid'
     *  - Tous les variants sont 'adult' → retourne 'adult'
     *  - Mix des deux                   → retourne 'both'
     *
     * Cas concret : le groupe "T-shirt Short" contient des tailles 2-14 ans
     * ET des tailles adulte → age_group = 'both'
     *
     * @param  array  $variants  Liste des variants MySQL du groupe
     * @return string            'adult' | 'kid' | 'both'
     */
    private function resolveGroupAgeGroup(array $variants): string
    {
        $uniqueGroups = collect($variants)
            ->pluck('age_group')
            ->unique()
            ->values()
            ->toArray();

        // Un seul age_group distinct → on le retourne directement
        if (count($uniqueGroups) === 1) {
            return $uniqueGroups[0];
        }

        // Plusieurs age_groups → groupe mixte
        return 'both';
    }
}
