<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Job asynchrone de récupération du label PDF Sendcloud.
 *
 * Pourquoi ce job existe :
 *   L'API Sendcloud v3 génère les labels en asynchrone.
 *   Au moment de la création du shipment, le parcel est en statut
 *   "ANNOUNCING" et le label n'est pas encore disponible.
 *   Ce job est dispatché avec un délai de 60 secondes après la création,
 *   le temps que Sendcloud génère le PDF côté transporteur.
 *
 * Retry : 5 tentatives avec backoff progressif
 *   (1min, 3min, 5min, 10min, 15min)
 *   Couvre les cas où Colissimo/Chronopost tarde à répondre.
 *
 * Résultats :
 *   - Label trouvé   → order.shipping_label_url = URL PDF Sendcloud
 *   - Label en cours → on retente (throw exception → retry auto)
 *   - Erreur fatale  → order.shipping_status = 'label_failed'
 */
class FetchShipmentLabelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int   $tries   = 5;
    public array $backoff = [60, 180, 300, 600, 900];

    // -------------------------------------------------------
    // 🎓 ON PASSE L'ORDER_ID (int), PAS LE MODÈLE
    // En passant le modèle, Laravel le sérialise en JSON dans
    // la table jobs. Si la commande est modifiée entre le dispatch
    // et l'exécution, on travaillerait sur une version stale.
    // Avec l'ID, on recharge toujours la version fraîche depuis la BDD.
    // -------------------------------------------------------
    public function __construct(
        private readonly int $orderId,
        private readonly int $parcelId
    ) {}

    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (! $order) {
            Log::warning('[FetchShipmentLabelJob] Order not found', [
                'order_id'  => $this->orderId,
                'parcel_id' => $this->parcelId,
            ]);
            return;
        }

        // Si le label a déjà été récupéré (ex: retry inutile), on sort
        if ($order->shipping_label_url && ! str_starts_with($order->shipping_label_url, 'sendcloud:parcel:')) {
            Log::info('[FetchShipmentLabelJob] Label already set, skipping', [
                'order_id' => $this->orderId,
            ]);
            return;
        }

        Log::info('[FetchShipmentLabelJob] Fetching label', [
            'order_id'  => $this->orderId,
            'parcel_id' => $this->parcelId,
            'attempt'   => $this->attempts(),
        ]);

        $labelUrl = $this->fetchLabelUrl();

        if ($labelUrl) {
            // -------------------------------------------------------
            // 🎓 ON STOCKE L'URL DU PDF SENDCLOUD
            // Cette URL nécessite une authentification Basic HTTP pour
            // être accessible. En production, deux options :
            //   A) Proxy via Laravel (route qui stream le PDF)
            //   B) Stocker le PDF sur S3 et donner une URL publique
            // Pour l'instant on stocke l'URL Sendcloud — l'admin peut
            // y accéder depuis Filament avec les credentials API.
            // -------------------------------------------------------
            $order->update([
                'shipping_label_url' => $labelUrl,
                'shipping_status'    => 'label_ready',
            ]);

            Log::info('[FetchShipmentLabelJob] Label URL saved', [
                'order_id'  => $this->orderId,
                'label_url' => $labelUrl,
            ]);

            return;
        }

        // -------------------------------------------------------
        // 🎓 PAS ENCORE PRÊT → ON RELANCE LE JOB
        // En lançant une exception, Laravel remet le job en queue
        // avec le délai de backoff défini dans $backoff.
        // C'est le pattern standard pour les retries conditionnels.
        // -------------------------------------------------------
        Log::info('[FetchShipmentLabelJob] Label not ready yet, retrying', [
            'order_id'  => $this->orderId,
            'parcel_id' => $this->parcelId,
            'attempt'   => $this->attempts(),
        ]);

        throw new \RuntimeException("Label not ready for parcel {$this->parcelId}, will retry.");
    }

    // -------------------------------------------------------
    // 🎓 RÉCUPÉRATION DE L'URL DU LABEL
    // On interroge GET /api/v3/parcels/{id} pour voir si le
    // parcel a un document label disponible dans documents[].
    // Sendcloud retourne un tableau documents avec type="label".
    // -------------------------------------------------------
    private function fetchLabelUrl(): ?string
    {
        $response = Http::withBasicAuth(
            config('services.sendcloud.public_key'),
            config('services.sendcloud.secret_key')
        )
        ->acceptJson()
        ->get("https://panel.sendcloud.sc/api/v3/parcels/{$this->parcelId}");

        if (! $response->successful()) {
            Log::warning('[FetchShipmentLabelJob] Sendcloud parcel fetch failed', [
                'parcel_id' => $this->parcelId,
                'status'    => $response->status(),
                'body'      => $response->body(),
            ]);
            return null;
        }

        $parcel = $response->json('data');

        // Cherche un document de type label dans la réponse
        $documents = $parcel['documents'] ?? [];
        foreach ($documents as $doc) {
            if (($doc['type'] ?? '') === 'label') {
                return $doc['link'] ?? null;
            }
        }

        // Fallback : vérifie le tracking_number (indique que le label est prêt)
        $trackingNumber = $parcel['tracking_number'] ?? null;
        if ($trackingNumber && $trackingNumber !== '') {
            // Construit l'URL standard du label Sendcloud
            return "https://panel.sendcloud.sc/api/v3/parcels/{$this->parcelId}/documents/label";
        }

        return null;
    }

    /**
     * Appelé après épuisement de toutes les tentatives.
     * La commande reste avec shipping_status = 'label_failed'
     * pour traitement manuel depuis Filament.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[FetchShipmentLabelJob] All retries exhausted', [
            'order_id'  => $this->orderId,
            'parcel_id' => $this->parcelId,
            'error'     => $exception->getMessage(),
        ]);

        $order = Order::find($this->orderId);
        $order?->update(['shipping_status' => 'label_failed']);
    }
}
