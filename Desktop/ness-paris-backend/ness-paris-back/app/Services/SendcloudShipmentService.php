<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendcloudShipmentService
{
    private const FALLBACK_WEIGHT_GRAMS = 250;


    public function createShipmentForOrder(Order $order): array
    {
        if (empty($order->sendcloud_shipping_method_id)) {
            Log::error('Sendcloud: sendcloud_shipping_method_id manquant', ['order_id' => $order->id]);
            $order->update(['shipping_status' => 'label_failed']);
            return ['success' => false, 'message' => 'Aucun sendcloud_shipping_method_id sur la commande.'];
        }

        // -------------------------------------------------------
        // 🎓 ÉTAPE 1 : récupérer le shipping_option_code v3
        // En v3, on ne passe plus un int (1068) mais un code
        // comme "colissimo:home/fr". On utilise l'endpoint
        // de compatibilité Sendcloud pour faire la conversion.
        // -------------------------------------------------------
        $shippingOptionCode = $this->resolveShippingOptionCode(
            (int) $order->sendcloud_shipping_method_id
        );

        if (! $shippingOptionCode) {
            Log::error('Sendcloud: impossible de résoudre le shipping_option_code', [
                'order_id'                    => $order->id,
                'sendcloud_shipping_method_id' => $order->sendcloud_shipping_method_id,
            ]);
            $order->update(['shipping_status' => 'label_failed']);
            return ['success' => false, 'message' => 'shipping_option_code introuvable.'];
        }

        // -------------------------------------------------------
        // 🎓 ÉTAPE 2 : calculer le poids réel depuis les lignes
        // weight en grammes → envoyé tel quel avec unit='g'
        // -------------------------------------------------------
        $weightGrams = $this->calculateWeightGrams($order);

        // -------------------------------------------------------
        // 🎓 PAYLOAD API v3 Shipments
        // Différences clés vs v2 :
        // - from_address = sender_address_id uniquement
        // - to_address   = address_line_1 (pas street)
        // - ship_with    = {type, properties}
        // - parcels      = [{weight: {value, unit}}]
        // -------------------------------------------------------
        $payload = json_encode([
            'from_address' => [
               'sender_address_id' => (int) config('services.sendcloud.sender_address_id'),
            ],
            'to_address' => [
                'name'          => $order->customer_name,
                'company_name'  => $order->customer_company,
                'email'         => $order->customer_email,
                'phone_number'  => $order->customer_phone,
                'address_line_1' => $order->shipping_address,
                'city'          => $order->shipping_city,
                'postal_code'   => $order->shipping_zip,
                'country_code'  => strtoupper($order->shipping_country),
            ],
            'ship_with' => [
                'type'       => 'shipping_option_code',
                'properties' => ['shipping_option_code' => $shippingOptionCode],
            ],
            'external_reference_id' => $order->number,
            'parcels' => [
                ['weight' => ['value' => $weightGrams, 'unit' => 'g']],
            ],
        ]);

        // Point relais optionnel
        if (! empty($order->sendcloud_service_point_id)) {
            $decoded = json_decode($payload, true);
            $decoded['to_service_point'] = ['id' => (int) $order->sendcloud_service_point_id];
            $payload = json_encode($decoded);
        }

        try {
            Log::info('Sendcloud v3: création shipment', [
                'order_id'             => $order->id,
                'weight_grams'         => $weightGrams,
                'shipping_option_code' => $shippingOptionCode,
            ]);

            $response = $this->http()
                ->withBody($payload, 'application/json')
                ->post('https://panel.sendcloud.sc/api/v3/shipments');

            if (! $response->successful()) {
                throw new \RuntimeException($response->body());
            }

            $data   = $response->json('data');
            $parcel = $data['parcels'][0] ?? [];

            // -------------------------------------------------------
            // 🎓 EN V3 : le label arrive en ASYNC
            // Le parcel est en statut "ANNOUNCING" au retour.
            // Le tracking_number peut être vide initialement.
            // On stocke l'ID du parcel pour récupérer le label ensuite.
            // -------------------------------------------------------
            $order->update([
                'shipping_status'          => 'label_created',
                'shipping_tracking_number' => $parcel['tracking_number'] ?? null,
                'shipping_tracking_url'    => $parcel['tracking_url'] ?? null,
                'shipping_carrier'         => $data['carrier']['code'] ?? $order->shipping_carrier,
                'shipped_at'               => now(),
                // On stocke l'ID du parcel Sendcloud pour récupérer le label PDF
                // via GET /api/v3/parcels/{id}/documents/label
                'shipping_label_url'       => 'sendcloud:parcel:' . ($parcel['id'] ?? ''),
            ]);
                            // Dispatch le job de récupération du label avec 60s de délai
                $parcelId = (int) ($parcel['id'] ?? 0);
                if ($parcelId > 0) {
                    \App\Jobs\FetchShipmentLabelJob::dispatch($order->id, $parcelId)
                        ->delay(now()->addSeconds(60));

                    Log::info('Sendcloud: FetchShipmentLabelJob dispatché', [
                        'order_id'  => $order->id,
                        'parcel_id' => $parcelId,
                        'delay'     => '60s',
                    ]);
                }
            Log::info('Sendcloud v3: shipment créé', [
                'order_id'  => $order->id,
                'parcel_id' => $parcel['id'] ?? null,
                'status'    => $parcel['status']['code'] ?? null,
            ]);

            return ['success' => true, 'data' => $data];

        } catch (\Throwable $e) {
            Log::error('Sendcloud v3: échec création shipment', [
                'order_id' => $order->id,
                'message'  => $e->getMessage(),
            ]);

            $order->update(['shipping_status' => 'label_failed']);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -------------------------------------------------------
    // 🎓 RÉSOLUTION DU SHIPPING OPTION CODE
    // Convertit un shipping_method_id v2 (ex: 1068)
    // en shipping_option_code v3 (ex: "colissimo:home/fr")
    // via l'endpoint de compatibilité Sendcloud.
    // On met en cache le résultat pour éviter un appel API
    // à chaque commande (même méthode = même code).
    // -------------------------------------------------------
    public function resolveShippingOptionCode(int $shippingMethodId): ?string
    {
        $cacheKey = "sendcloud_option_code_{$shippingMethodId}";

        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($shippingMethodId) {
            $response = $this->http()->post(
                'https://panel.sendcloud.sc/api/v3/compat/shipping-options',
                ['shipping_method_ids' => [$shippingMethodId]]
            );

            if (! $response->successful()) {
                return null;
            }

            return $response->json("data.{$shippingMethodId}");
        });
    }

    // -------------------------------------------------------
    // 🎓 RÉCUPÉRER LE LABEL PDF D'UN PARCEL
    // À appeler depuis Filament après création du shipment.
    // Retourne l'URL ou le contenu base64 du PDF.
    // -------------------------------------------------------
    public function getLabelUrl(int $parcelId): ?string
    {
        $response = $this->http()
            ->accept('application/pdf')
            ->get("https://panel.sendcloud.sc/api/v3/parcels/{$parcelId}/documents/label");

        if (! $response->successful()) {
            Log::warning('Sendcloud: label non disponible', ['parcel_id' => $parcelId]);
            return null;
        }

        // Retourne l'URL finale (après redirect)
        return $response->effectiveUri()?->__toString()
            ?? "https://panel.sendcloud.sc/api/v3/parcels/{$parcelId}/documents/label";
    }

    private function calculateWeightGrams(Order $order): int
    {
        $order->loadMissing('lines.product');

        $total = $order->lines->sum(
            fn ($line) => ($line->product?->weight_grams ?? self::FALLBACK_WEIGHT_GRAMS) * $line->qty
        );

        return max($total, self::FALLBACK_WEIGHT_GRAMS);
    }

    private function http()
    {
        return Http::withBasicAuth(
            config('services.sendcloud.public_key'),
            config('services.sendcloud.secret_key')
        )->acceptJson();
    }
}
