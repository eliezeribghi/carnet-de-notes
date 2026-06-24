<?php

namespace App\Services;

use App\Models\Product;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class ShippingService
{
    public const FREE_SHIPPING_THRESHOLD_CENTS = 100000; // 1000 €

    private Client $client;

    public function __construct()
    {
       $this->client = new Client([
                'base_uri' => rtrim(config('services.sendcloud.base_url'), '/') . '/',
                'auth' => [
                    config('services.sendcloud.public_key'),
                    config('services.sendcloud.secret_key'),
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 10,
            ]);
    }

    public function getOptions(
        int $totalWeightGrams,
        int $subtotalCents,
        string $toCountry = 'FR',
        string $toPostalCode = '75000'
    ): array {
        $isFree = $subtotalCents >= self::FREE_SHIPPING_THRESHOLD_CENTS;
        $weightKg = $totalWeightGrams / 1000;

        try {
            $response = $this->client->get('shipping_methods', [
                'query' => [
                    'to_country' => strtoupper($toCountry),
                    'from_country' => 'FR',
                    'weight' => $weightKg,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $methods = $data['shipping_methods'] ?? [];

            $options = [];

            foreach ($methods as $method) {
                if (($method['service_point_input'] ?? null) === 'required') {
                    continue;
                }

                if (($method['id'] ?? null) === 8) {
                    continue;
                }

                $carrier = strtolower((string) ($method['carrier'] ?? 'unknown'));
                $methodId = (string) ($method['id'] ?? '');
                $methodName = (string) ($method['name'] ?? 'Livraison');

                $minKg = (float) ($method['min_weight'] ?? 0);
                $maxKg = (float) ($method['max_weight'] ?? 999999);

                if ($weightKg < $minKg || $weightKg > $maxKg) {
                    continue;
                }

                $country = collect($method['countries'] ?? [])
                    ->firstWhere('iso_2', strtoupper($toCountry));

                $customPriceCents = $this->getCustomRateCents($carrier, $totalWeightGrams, $toCountry);

                if ($customPriceCents !== null) {
                    $priceCents = $customPriceCents;
                } else {
                    $priceEuros = (float) ($country['price'] ?? 0);
                    if ($priceEuros <= 0) {
                        continue;
                    }
                    $priceCents = (int) round($priceEuros * 100);
                }

                if ($isFree) {
                    $priceCents = 0;
                }

                $leadTime = $country['lead_time_hours'] ?? null;

                $options[] = [
                    'key' => $methodId,
                    'label' => $methodName,
                    'carrier' => $carrier,
                    'delays' => $leadTime
                        ? ceil(((int) $leadTime) / 24) . ' jours ouvrés'
                        : 'Délai variable',
                    'price_cents' => $priceCents,
                    'price_euros' => $priceCents / 100,
                    'is_free' => $isFree,
                    'sendcloud_shipping_method_id' => (int) ($method['id'] ?? 0),
                    'sendcloud_checkout_option_id' => null,
                    'requires_service_point' => (($method['service_point_input'] ?? null) === 'required'),
                ];
            }

            $options = $this->deduplicateOptions($options);

            usort($options, fn ($a, $b) => $a['price_cents'] <=> $b['price_cents']);

            Log::info('Shipping options resolved', [
                'country' => strtoupper($toCountry),
                'postal_code' => $toPostalCode,
                'weight_grams' => $totalWeightGrams,
                'subtotal_cents' => $subtotalCents,
                'source' => count($options) > 0 ? 'sendcloud+override' : 'fallback',
                'options' => $options,
            ]);

            if (count($options) > 0) {
                return $options;
            }

            return $this->getFallbackOptions($totalWeightGrams, $subtotalCents, $toCountry);
        } catch (RequestException $e) {
            Log::error('Sendcloud error', [
                'message' => $e->getMessage(),
                'country' => strtoupper($toCountry),
                'postal_code' => $toPostalCode,
                'weight_grams' => $totalWeightGrams,
            ]);

            return $this->getFallbackOptions($totalWeightGrams, $subtotalCents, $toCountry);
        }
    }

    public function calculateCartWeight(iterable $cartItems): int
    {
        $totalGrams = 0;

        foreach ($cartItems as $item) {
            $product = Product::where('sku', $item->sku)->first();

            if ($product) {
                $totalGrams += ((int) ($product->weight_grams ?? 250)) * (int) $item->quantity;
            }
        }

        return $totalGrams;
    }

    private function getCustomRateCents(string $carrier, int $totalWeightGrams, string $country): ?int
    {
        $carrier = strtolower($carrier);
        $country = strtoupper($country);

        $matrix = $country === 'FR'
            ? [
                'colissimo' => [
                    [500, 690],
                    [1000, 790],
                    [2000, 990],
                    [5000, 1490],
                    [10000, 2200],
                    [PHP_INT_MAX, 3500],
                ],
                'chronopost' => [
                    [500, 1250],
                    [1000, 1450],
                    [2000, 1650],
                    [5000, 2200],
                    [10000, 3500],
                    [PHP_INT_MAX, 5000],
                ],
            ]
            : [
                'dhl' => [
                    [2000, 1800],
                    [5000, 2400],
                    [10000, 3200],
                    [PHP_INT_MAX, 4800],
                ],
                'ups' => [
                    [2000, 1500],
                    [5000, 2000],
                    [10000, 2800],
                    [PHP_INT_MAX, 4200],
                ],
            ];

        if (! isset($matrix[$carrier])) {
            return null;
        }

        foreach ($matrix[$carrier] as [$maxWeight, $priceCents]) {
            if ($totalWeightGrams <= $maxWeight) {
                return $priceCents;
            }
        }

        return null;
    }

    private function deduplicateOptions(array $options): array
    {
        $bestByCarrier = [];

        foreach ($options as $option) {
            $carrier = $option['carrier'] ?? 'unknown';

            if (! isset($bestByCarrier[$carrier])) {
                $bestByCarrier[$carrier] = $option;
                continue;
            }

            if (($option['price_cents'] ?? PHP_INT_MAX) < ($bestByCarrier[$carrier]['price_cents'] ?? PHP_INT_MAX)) {
                $bestByCarrier[$carrier] = $option;
            }
        }

        return array_values($bestByCarrier);
    }

    private function getFallbackOptions(
        int $totalWeightGrams,
        int $subtotalCents,
        string $country
    ): array {
        $isFree = $subtotalCents >= self::FREE_SHIPPING_THRESHOLD_CENTS;
        $isFrance = strtoupper($country) === 'FR';

        $rates = $isFrance
            ? [
                [
                    'key' => 'colissimo',
                    'label' => 'Colissimo 48h',
                    'carrier' => 'colissimo',
                    'delays' => '2-3 jours',
                    'tranches' => [
                        [500, 690],
                        [1000, 790],
                        [2000, 990],
                        [5000, 1490],
                        [10000, 2200],
                        [PHP_INT_MAX, 3500],
                    ],
                ],
                [
                    'key' => 'chronopost',
                    'label' => 'Chronopost 24h',
                    'carrier' => 'chronopost',
                    'delays' => '1 jour',
                    'tranches' => [
                        [500, 1250],
                        [1000, 1450],
                        [2000, 1650],
                        [5000, 2200],
                        [10000, 3500],
                        [PHP_INT_MAX, 5000],
                    ],
                ],
            ]
            : [
                [
                    'key' => 'dhl',
                    'label' => 'DHL Express Europe',
                    'carrier' => 'dhl',
                    'delays' => '3-5 jours',
                    'tranches' => [
                        [2000, 1800],
                        [5000, 2400],
                        [10000, 3200],
                        [PHP_INT_MAX, 4800],
                    ],
                ],
                [
                    'key' => 'ups',
                    'label' => 'UPS Standard Europe',
                    'carrier' => 'ups',
                    'delays' => '5-7 jours',
                    'tranches' => [
                        [2000, 1500],
                        [5000, 2000],
                        [10000, 2800],
                        [PHP_INT_MAX, 4200],
                    ],
                ],
            ];

        return array_map(function ($carrier) use ($totalWeightGrams, $isFree) {
            $priceCents = 0;

            foreach ($carrier['tranches'] as [$max, $price]) {
                if ($totalWeightGrams <= $max) {
                    $priceCents = $price;
                    break;
                }
            }

            if ($isFree) {
                $priceCents = 0;
            }

            return [
                'key' => $carrier['key'],
                'label' => $carrier['label'],
                'carrier' => $carrier['carrier'],
                'delays' => $carrier['delays'],
                'price_cents' => $priceCents,
                'price_euros' => $priceCents / 100,
                'is_free' => $isFree,
            ];
        }, $rates);
    }
}
