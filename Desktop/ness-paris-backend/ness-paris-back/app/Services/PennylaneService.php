<?php
// =============================================================================
// app/Services/PennylaneService.php
//
// SÉCURITÉ DEV/PROD :
//   PENNYLANE_ENABLED=false → tous les appels ignorés silencieusement (dev)
//   PENNYLANE_ENABLED=true  → appels réels (production uniquement)
//
// CONFIG REQUISE (.env) :
//   PENNYLANE_API_URL=https://app.pennylane.com/api/external/v2
//   PENNYLANE_API_KEY=ta_clé_ici
//   PENNYLANE_ENABLED=false   ← false en dev, true en prod
//
// CONFIG REQUISE (config/services.php) :
//   'pennylane' => [
//       'api_url' => env('PENNYLANE_API_URL'),
//       'api_key'  => env('PENNYLANE_API_KEY'),
//       'enabled'  => env('PENNYLANE_ENABLED', false),
//   ],
// =============================================================================

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PennylaneService
{
    private string $baseUrl;
    private string $apiKey;
    private bool   $enabled;

    private const CACHE_TTL = 30;

    public function __construct()
    {
        $this->baseUrl = config('services.pennylane.api_url');
        $this->apiKey  = config('services.pennylane.api_key');
        $this->enabled = (bool) config('services.pennylane.enabled', false);
    }

    // =========================================================================
    // GUARD
    // Vérifie si Pennylane est activé avant chaque appel API.
    // Si PENNYLANE_ENABLED=false → log info + return true (= désactivé).
    // =========================================================================
    private function isDisabled(string $context): bool
    {
        if (!$this->enabled) {
            Log::info("[Pennylane] Désactivé (PENNYLANE_ENABLED=false) — {$context} ignoré");
            return true;
        }
        return false;
    }

    private function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(10);
    }

    // =========================================================================
    // CLIENTS
    // =========================================================================

    public function createCustomer(array $data, int $userId): ?string
    {
        if ($this->isDisabled('createCustomer')) return null;

        try {
            $billingAddress = [
                'address'        => $data['address']     ?? '',
                'postal_code'    => $data['postal_code'] ?? '',
                'city'           => $data['city']        ?? '',
                'country_alpha2' => $data['country']     ?? 'FR',
            ];

            $payload = array_filter([
                'name'               => $data['company_name'] ?? $data['name'],
                'emails'             => [$data['email']],
                'vat_number'         => $data['vat_number'] ?? null,
                'external_reference' => (string) $userId,
                'billing_address'    => $billingAddress,
            ], fn($v) => $v !== null && $v !== '' && $v !== []);

            $response = $this->http()->post('/company_customers', $payload);

            if ($response->successful()) {
                $pennylaneId = (string) $response->json('id');
                Log::info('[Pennylane] Client créé avec succès', [
                    'user_id'      => $userId,
                    'pennylane_id' => $pennylaneId,
                    'company_name' => $data['company_name'] ?? $data['name'],
                ]);
                return $pennylaneId;
            }

            Log::error('[Pennylane] Échec création client', [
                'user_id'  => $userId,
                'status'   => $response->status(),
                'response' => $response->json(),
            ]);
            return null;

        } catch (\Throwable $e) {
            Log::error('[Pennylane] Exception création client', [
                'user_id' => $userId,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getCustomer(string $pennylaneId): ?array
    {
        if ($this->isDisabled('getCustomer')) return null;

        return Cache::remember(
            "pennylane_customer_{$pennylaneId}",
            now()->addMinutes(self::CACHE_TTL),
            function () use ($pennylaneId) {
                try {
                    $response = $this->http()->get("/company_customers/{$pennylaneId}");
                    if ($response->successful()) {
                        return $response->json();
                    }
                    Log::warning('[Pennylane] Client introuvable', [
                        'pennylane_id' => $pennylaneId,
                        'status'       => $response->status(),
                    ]);
                    return null;
                } catch (\Throwable $e) {
                    Log::error('[Pennylane] Exception lecture client', [
                        'pennylane_id' => $pennylaneId,
                        'message'      => $e->getMessage(),
                    ]);
                    return null;
                }
            }
        );
    }

    public function updateCustomer(string $pennylaneId, array $data): bool
    {
        if ($this->isDisabled('updateCustomer')) return false;

        try {
            $response = $this->http()->put("/company_customers/{$pennylaneId}", $data);
            if ($response->successful()) {
                Cache::forget("pennylane_customer_{$pennylaneId}");
                Log::info('[Pennylane] Client mis à jour', ['pennylane_id' => $pennylaneId]);
                return true;
            }
            Log::error('[Pennylane] Échec mise à jour client', [
                'pennylane_id' => $pennylaneId,
                'status'       => $response->status(),
                'response'     => $response->json(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('[Pennylane] Exception mise à jour client', [
                'pennylane_id' => $pennylaneId,
                'message'      => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function clearCustomerCache(string $pennylaneId): void
    {
        Cache::forget("pennylane_customer_{$pennylaneId}");
    }

    // =========================================================================
    // FACTURES
    // =========================================================================

    public function createInvoice(Order $order): ?string
    {
        if ($this->isDisabled('createInvoice')) return null;

        $pennylaneId = $order->customer?->pennylane_customer_id;
        if (!$pennylaneId) {
            Log::warning('[Pennylane] pennylane_customer_id manquant', [
                'order_id'    => $order->id,
                'customer_id' => $order->customer_id,
            ]);
            return null;
        }

        try {
            $lineItems = $order->lines->map(fn ($line) => [
                'label'                   => trim("{$line->name_snapshot} {$line->color_snapshot} {$line->size_snapshot}"),
                'quantity'                => (int) $line->qty,
                'raw_currency_unit_price' => (string) round($line->unit_price_cents / 100, 2),
                'vat_rate'                => 'FR_200',
                'unit'                    => 'piece',
            ])->toArray();

            if ($order->shipping_cents > 0) {
                $lineItems[] = [
                    'label'                   => "Livraison — {$order->shipping_method_label}",
                    'quantity'                => 1,
                    'raw_currency_unit_price' => (string) round($order->shipping_cents / 100, 2),
                    'vat_rate'                => 'FR_200',
                    'unit'                    => 'piece',
                ];
            }

            $response = $this->http()->post('/customer_invoices', [
                'customer_id'        => (int) $pennylaneId,
                'date'               => ($order->paid_at ?? now())->format('Y-m-d'),
                'deadline'           => now()->format('Y-m-d'),
                'external_reference' => $order->number,
                'invoice_lines'      => $lineItems,
            ]);

            if ($response->successful()) {
                $invoiceId = (string) $response->json('id');
                Log::info('[Pennylane] Facture créée', [
                    'order_id'   => $order->id,
                    'invoice_id' => $invoiceId,
                ]);
                return $invoiceId;
            }

            Log::error('[Pennylane] Échec création facture', [
                'order_id' => $order->id,
                'status'   => $response->status(),
                'response' => $response->json(),
            ]);
            return null;

        } catch (\Throwable $e) {
            Log::error('[Pennylane] Exception création facture', [
                'order_id' => $order->id,
                'message'  => $e->getMessage(),
            ]);
            return null;
        }
    }
}