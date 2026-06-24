<?php

namespace App\Services\Verification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Vérifie une entreprise française via l'API Sirene de l'INSEE.
 * API gratuite, mise à jour quotidiennement, sans authentification requise.
 * Documentation : https://api.insee.fr/catalogue/site/themes/wso2/subthemes/insee/pages/item-info.jag?name=Sirene&version=V3&provider=insee
 */
class SireneVerificationService
{
    private const BASE_URL = 'https://api.insee.fr/api-sirene/3.11';
  

    /**
     * Vérifie un SIREN (9 chiffres) auprès de l'INSEE.
     */
    public function verifySiren(string $siren): array
    {
        $siren = preg_replace('/\D/', '', $siren);

        if (strlen($siren) !== 9) {
            return $this->result(false, 'Invalid SIREN (must be 9 digits).', []);
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept'                       => 'application/json',
                    'X-INSEE-Api-Key-Integration'  => config('services.insee.api_key'),
                ])
                ->get(self::BASE_URL . "/siren/{$siren}");

            if ($response->status() === 404) {
                return $this->result(false, 'SIREN not found in INSEE database.', []);
            }

            if ($response->status() === 410) {
                return $this->result(false, 'Company struck off (SIREN deleted).', []);
            }

            if (! $response->successful()) {
                Log::warning('[Sirene] API error', [
                    'siren'  => $siren,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                // Erreur technique → on ne rejette pas, on laisse en pending pour retry
                return $this->result(null, 'INSEE technical error, verification deferred.', []);
            }

            $data        = $response->json();
            $uniteLegale = $data['uniteLegale'] ?? [];

            // etatAdministratifUniteLegale = 'A' signifie entreprise active
            $etat = $uniteLegale['periodesUniteLegale'][0]['etatAdministratifUniteLegale'] ?? null;

            if ($etat !== 'A') {
                return $this->result(false, 'Company inactive or ceased according to INSEE.', $uniteLegale);
            }

            return $this->result(true, 'Valid SIREN, active company.', [
                'siren'        => $siren,
                'denomination' => $uniteLegale['periodesUniteLegale'][0]['denominationUniteLegale'] ?? null,
                'etat'         => $etat,
                'source'       => 'sirene',
            ]);
        } catch (\Exception $e) {
            Log::error('[Sirene] Exception', ['siren' => $siren, 'error' => $e->getMessage()]);
            return $this->result(null, 'INSEE network exception: ' . $e->getMessage(), []);
        }
    }

    /**
     * Vérifie un SIRET (14 chiffres) auprès de l'INSEE.
     * Le SIRET identifie un établissement précis (SIREN + NIC).
     */
    public function verifySiret(string $siret): array
    {
        $siret = preg_replace('/\D/', '', $siret);

        if (strlen($siret) !== 14) {
            return $this->result(false, 'Invalid SIRET (must be 14 digits).', []);
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept'                       => 'application/json',
                    'X-INSEE-Api-Key-Integration'  => config('services.insee.api_key'),
                ])
                ->get(self::BASE_URL . "/siret/{$siret}");

            if ($response->status() === 404) {
                return $this->result(false, 'SIRET not found in INSEE database.', []);
            }

            if ($response->status() === 410) {
                return $this->result(false, 'Establishment closed (SIRET deleted).', []);
            }

            if (! $response->successful()) {
                Log::warning('[Sirene] SIRET API error', [
                    'siret'  => $siret,
                    'status' => $response->status(),
                ]);
                return $this->result(null, 'INSEE technical error, verification deferred.', []);
            }

            $data          = $response->json();
            $etablissement = $data['etablissement'] ?? [];

            $etat = $etablissement['periodesEtablissement'][0]['etatAdministratifEtablissement'] ?? null;

            if ($etat !== 'A') {
                return $this->result(false, 'Establishment inactive according to INSEE.', $etablissement);
            }

            return $this->result(true, 'Valid SIRET, active establishment.', [
                'siret'  => $siret,
                'siren'  => substr($siret, 0, 9),
                'etat'   => $etat,
                'source' => 'sirene',
            ]);
        } catch (\Exception $e) {
            Log::error('[Sirene] SIRET exception', ['siret' => $siret, 'error' => $e->getMessage()]);
            return $this->result(null, 'INSEE network exception: ' . $e->getMessage(), []);
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
            'source'  => 'sirene',
        ];
    }
}
