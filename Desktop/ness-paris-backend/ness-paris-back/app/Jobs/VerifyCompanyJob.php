<?php

// =============================================================================
// app/Jobs/VerifyCompanyJob.php
//
// RESPONSABILITÉ :
//   Vérifie automatiquement une company après son inscription.
//   Choisit Sirene (INSEE) pour les entreprises françaises (country=FR),
//   ou VIES (UE) pour les entreprises européennes avec TVA.
//
// DISPATCHÉ PAR :
//   ClientAuthController::register() — hors transaction, après création compte
//
// RÉSULTAT :
//   - valid=true  → company.status = 'approved'
//   - valid=false → company.status = 'rejected'
//   - valid=null  → erreur technique → on laisse en 'pending_review' pour retry
//
// RETRY : 3 tentatives avec backoff exponentiel (60s, 120s, 240s)
// =============================================================================

namespace App\Jobs;

use App\Models\Company;
use App\Services\Verification\SireneVerificationService;
use App\Services\Verification\ViesVerificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class VerifyCompanyJob implements ShouldQueue
{
    use Queueable;

    // Nombre de tentatives max avant d'abandonner
    public int $tries = 3;

    // Backoff exponentiel : 60s, 120s, 240s entre les tentatives
    public array $backoff = [60, 120, 240];

    /**
     * @param int $companyId  ID de la company à vérifier
     */
    public function __construct(public int $companyId)
    {
        //
    }

    /**
     * Logique principale du job.
     *
     * ORDRE DE VÉRIFICATION :
     *   1. Si country=FR et SIREN présent → vérifie via INSEE Sirene
     *   2. Si vat_number présent → vérifie via VIES (UE)
     *   3. Sinon → on approuve manuellement (pas assez d'infos pour vérifier)
     */
    public function handle(
        SireneVerificationService $sirene,
        ViesVerificationService   $vies
    ): void {
        // Récupère la company — si supprimée entre temps, on abandonne
        $company = Company::find($this->companyId);

        if (!$company) {
            Log::warning('[VerifyCompanyJob] Company not found', ['id' => $this->companyId]);
            return;
        }

        Log::info('[VerifyCompanyJob] Starting verification', [
            'company_id' => $company->id,
            'country'    => $company->country,
            'siren'      => $company->siren,
            'vat_number' => $company->vat_number,
        ]);

        // ── Vérification française via INSEE Sirene ───────────────────────────
        if ($company->country === 'FR' && $company->siren) {
            $result = $sirene->verifySiren($company->siren);
            $this->applyResult($company, $result);
            return;
        }

        // ── Vérification européenne via VIES ─────────────────────────────────
        if ($company->vat_number) {
            $result = $vies->verifyVat($company->vat_number);
            $this->applyResult($company, $result);
            return;
        }

        // ── Pas assez d'infos → laisse en pending_review pour validation manuelle
        Log::info('[VerifyCompanyJob] No verification data, left pending', [
            'company_id' => $company->id,
        ]);
    }

    /**
     * Applique le résultat de vérification sur la company.
     *
     * @param Company $company
     * @param array   $result  ['valid' => bool|null, 'message' => string, ...]
     */
    private function applyResult(Company $company, array $result): void
    {
        if ($result['valid'] === true) {
            // Entreprise valide → on approuve automatiquement
            $company->update([
                'status'      => 'approved',
                'approved_at' => now(),
            ]);
            Log::info('[VerifyCompanyJob] Company approved', ['company_id' => $company->id]);

        } elseif ($result['valid'] === false) {
            // Entreprise invalide ou inactive → on rejette
            $company->update(['status' => 'rejected']);
            Log::info('[VerifyCompanyJob] Company rejected', [
                'company_id' => $company->id,
                'reason'     => $result['message'],
            ]);

        } else {
            // valid=null = erreur technique → on laisse en pending pour retry
            Log::warning('[VerifyCompanyJob] Verification deferred (technical error)', [
                'company_id' => $company->id,
                'message'    => $result['message'],
            ]);
        }
    }

    /**
     * Appelé quand toutes les tentatives ont échoué.
     * On laisse la company en pending_review → validation manuelle Filament.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('[VerifyCompanyJob] All retries failed', [
            'company_id' => $this->companyId,
            'error'      => $exception->getMessage(),
        ]);
    }
}
