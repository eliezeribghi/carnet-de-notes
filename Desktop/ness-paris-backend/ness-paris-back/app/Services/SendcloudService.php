<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendcloudService
{
    /**
     * Liste toutes les méthodes d'expédition disponibles.
     * Utile pour déboguer ou alimenter un select dans Filament.
     */
 public function listShippingMethods(): array
{
    $response = $this->http()->get(
        rtrim(config('services.sendcloud.base_url'), '/') . '/api/v3/shipping_methods'
    );

    if (! $response->successful()) {
        throw new \RuntimeException('Sendcloud listShippingMethods: ' . $response->body());
    }

    return $response->json();
}

    // -------------------------------------------------------
    // 🎓 RÉCUPÉRER UN PARCEL PAR SON ID SENDCLOUD
    //
    // Sendcloud assigne un ID interne à chaque parcel créé.
    // Cet ID est retourné dans parcel.id lors de la création.
    // Tu peux l'utiliser pour re-consulter l'état du colis.
    //
    // Note : on ne stocke pas encore cet ID en BDD.
    // Si tu veux le stocker, ajoute une colonne sendcloud_parcel_id
    // sur orders et sauvegarde $parcel['id'] dans createShipmentForOrder.
    // -------------------------------------------------------
    public function getParcel(int $parcelId): array
    {
        $response = $this->http()->get(
            config('services.sendcloud.base_url') . "/api/v3/parcels/{$parcelId}"
        );

        if (! $response->successful()) {
            throw new \RuntimeException("Sendcloud getParcel({$parcelId}): " . $response->body());
        }

        return $response->json('parcel', []);
    }

    // -------------------------------------------------------
    // 🎓 RÉCUPÉRER L'URL DU LABEL PDF
    //
    // Sendcloud génère le PDF côté serveur.
    // On retourne l'URL du PDF — tu peux faire un redirect
    // vers cette URL dans ton controller Filament pour l'imprimer.
    //
    // Format : https://panel.sendcloud.sc/api/v3/labels/normal_printer/...
    // -------------------------------------------------------
    public function getLabelUrl(int $parcelId): ?string
    {
        try {
            $parcel = $this->getParcel($parcelId);
            return $parcel['label']['normal_printer'][0] ?? null;
        } catch (\Throwable $e) {
            Log::error('Sendcloud: impossible de récupérer le label', [
                'parcel_id' => $parcelId,
                'error'     => $e->getMessage(),
            ]);
            return null;
        }
    }

    // -------------------------------------------------------
    // 🎓 ANNULER UN PARCEL
    //
    // À appeler quand tu annules une commande AVANT expédition.
    // Sendcloud ne peut pas annuler un parcel déjà scanné
    // par le transporteur.
    //
    // Retourne true si annulé, false sinon.
    // -------------------------------------------------------
    public function cancelParcel(int $parcelId): bool
    {
        $response = $this->http()->post(
            config('services.sendcloud.base_url') . "/api/v3/parcels/{$parcelId}/cancel"
        );

        if ($response->successful()) {
            Log::info('Sendcloud: parcel annulé', ['parcel_id' => $parcelId]);
            return true;
        }

        Log::warning('Sendcloud: annulation échouée', [
            'parcel_id' => $parcelId,
            'response'  => $response->body(),
        ]);

        return false;
    }

    // -------------------------------------------------------
    // 🎓 TRACKING D'UN PARCEL
    //
    // Retourne les infos de suivi pour affichage dans
    // le portail client B2B (page "mes commandes").
    // -------------------------------------------------------
    public function getTracking(int $parcelId): array
    {
        $parcel = $this->getParcel($parcelId);

        return [
            'tracking_number' => $parcel['tracking_number'] ?? null,
            'tracking_url'    => $parcel['tracking_url'] ?? null,
            'status'          => $parcel['status']['message'] ?? null,
            'carrier'         => $parcel['carrier']['code'] ?? null,
        ];
    }

    // -------------------------------------------------------
    // 🎓 CLIENT HTTP PARTAGÉ (Laravel Http Facade)
    //
    // Http::withBasicAuth() = authentification Basic HTTP
    // (identique à Guzzle 'auth' => [...] mais plus lisible).
    // Centralisé ici pour ne pas répéter les credentials.
    // -------------------------------------------------------
    private function http()
    {
        return Http::withBasicAuth(
            config('services.sendcloud.public_key'),
            config('services.sendcloud.secret_key')
        )->acceptJson()->contentType('application/json');
    }
}
