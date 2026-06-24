<?php

namespace App\Services\Verification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vérifie un numéro de TVA intracommunautaire via le système VIES de l'UE.
 * API gratuite, temps réel, maintenue par la Commission européenne.
 * Documentation : https://ec.europa.eu/taxation_customs/vies/#/technical-information
 */
class ViesVerificationService
{
    // Endpoint REST VIES — officiel Commission européenne
    private const BASE_URL = 'https://ec.europa.eu/taxation_customs/vies/rest-api/ms';

    // Pays UE couverts par VIES (XI = Irlande du Nord post-Brexit)
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES',
        'FI', 'FR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT',
        'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI',
    ];

    /**
     * Vérifie un numéro de TVA intracommunautaire.
     * Format accepté : "FR12345678901" ou "FR 12 345 678 901" (normalisé automatiquement).
     */
    public function verify(string $vatNumber): array
    {
        // Nettoyage : enlève espaces, tirets, points
        $vatNumber   = strtoupper(preg_replace('/[\s\.\-]/', '', $vatNumber));
        $countryCode = substr($vatNumber, 0, 2);
        $number      = substr($vatNumber, 2);

        if (strlen($vatNumber) < 4 || ! ctype_alpha($countryCode)) {
            return $this->result(false, 'Invalid VAT number format.', []);
        }

        if (! in_array($countryCode, self::EU_COUNTRIES)) {
            return $this->result(false, "Country {$countryCode} not covered by VIES.", []);
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['Accept' => 'application/json'])
                ->get(self::BASE_URL . "/{$countryCode}/vat/{$number}");

            if ($response->status() === 404) {
                return $this->result(false, 'VAT number not found in VIES.', []);
            }

            if (! $response->successful()) {
                Log::warning('[VIES] API error', [
                    'vat'    => $vatNumber,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                // VIES est parfois indisponible → on ne rejette pas, retry automatique
                return $this->result(null, 'VIES technical error, verification deferred.', []);
            }

            $data    = $response->json();
            $isValid = (bool) ($data['isValid'] ?? false);

            if (! $isValid) {
                return $this->result(false, 'VAT number invalid according to VIES.', $data);
            }

            return $this->result(true, 'Valid VAT number.', [
                'vat_number'   => $vatNumber,
                'country_code' => $countryCode,
                'name'         => $data['name']    ?? null,
                'address'      => $data['address'] ?? null,
                'source'       => 'vies',
            ]);

        } catch (\Exception $e) {
            Log::error('[VIES] Exception', ['vat' => $vatNumber, 'error' => $e->getMessage()]);
            return $this->result(null, 'VIES network exception: ' . $e->getMessage(), []);
        }
    }

    /**
     * Normalise la réponse du service.
     * @param bool|null $valid  true=valide, false=invalide, null=erreur technique (retry)
     */
    private function result(?bool $valid, string $message, array $meta): array
    {
        return [
            'valid'   => $valid,
            'message' => $message,
            'meta'    => $meta,
            'source'  => 'vies',
        ];
    }
}
