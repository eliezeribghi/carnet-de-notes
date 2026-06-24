<?php
// =============================================================================
// app/Http/Controllers/Api/ClientAuthController.php
//
// RESPONSABILITÉ :
//   Authentification client B2B (inscription, connexion, déconnexion,
//   mot de passe oublié / réinitialisation).
//
// FLUX INSCRIPTION :
//   1. Valide les données (RegisterClientRequest)
//   2. Transaction BDD → Company + User créés atomiquement
//   3. Hors transaction → PennylaneService::createCustomer() (non bloquant)
//   4. Hors transaction → VerifyCompanyJob dispatché (SIREN/VIES en arrière-plan)
//
// POURQUOI Pennylane et VerifyCompanyJob HORS transaction ?
//   Les appels externes (API Pennylane, INSEE, VIES) ne doivent jamais
//   être dans une transaction BDD. Si l'API est lente ou indisponible,
//   la transaction resterait ouverte et bloquerait les connexions.
//   Un échec externe ne doit pas annuler la création du compte.
//
// RESET PASSWORD :
//   La logique est mutualisée dans le trait HandlesPasswordReset (broker 'users').
// =============================================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesPasswordReset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ForgotPasswordRequest;
use App\Http\Requests\Client\LoginClientRequest;
use App\Http\Requests\Client\RegisterClientRequest;
use App\Http\Requests\Client\ResetPasswordRequest;
use App\Jobs\VerifyCompanyJob;
use App\Models\Company;
use App\Models\User;
use App\Services\PennylaneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ClientAuthController extends Controller
{
    use HandlesPasswordReset;

    // =========================================================================
    // INSCRIPTION
    // =========================================================================

    /**
     * Crée un compte client B2B.
     *
     * ÉTAPES :
     *   1. Transaction atomique → Company + User en BDD
     *   2. PennylaneService::createCustomer() → fiche client dans Pennylane
     *      (non bloquant — si Pennylane échoue, l'inscription continue)
     *   3. VerifyCompanyJob::dispatch() → vérification SIREN/VIES en arrière-plan
     *      (non bloquant — la company reste en 'pending_review' jusqu'à vérification)
     */
    public function register(RegisterClientRequest $request): JsonResponse
    {
        $data = $request->validated();

        // ── Transaction BDD ───────────────────────────────────────────────────
        // Atomique : si la création du User échoue, la Company est aussi annulée.
        // On ne met PAS les appels externes ici (Pennylane, INSEE).
        $result = DB::transaction(function () use ($data) {
            $company = Company::create([
                'name'        => $data['company_name'],
                'legal_name'  => $data['legal_name'] ?? $data['company_name'],
                'vat_number'  => $data['vat_number'] ?? null,
                'siren'       => $data['siren'] ?? null,
                'siret'       => $data['siret'] ?? null,
                'email'       => $data['company_email'] ?? $data['email'],
                'phone'       => $data['company_phone'] ?? null,
                'country'     => $data['country'] ?? 'FR',
                'status'      => 'pending_review',
                'is_active'   => true,
                'approved_at' => null,
                'approved_by' => null,
            ]);

            $user = User::create([
                'company_id' => $company->id,
                'name'       => $data['name'],
                'email'      => $data['email'],
                'password'   => Hash::make($data['password']),
                'role'       => 'client',
                'is_admin'   => false,
                'is_active'  => true,
            ]);

            return compact('company', 'user');
        });

        // ── Pennylane : création fiche client ────────────────────────────────
        // Hors transaction — un échec Pennylane ne doit pas annuler l'inscription.
        // Si PENNYLANE_ENABLED=false (dev), cette méthode retourne null silencieusement.
        $pennylaneId = app(PennylaneService::class)
            ->createCustomer($data, $result['user']->id);

        if ($pennylaneId) {
            $result['user']->update(['pennylane_customer_id' => $pennylaneId]);
        }

        // ── Vérification SIREN/VIES en arrière-plan ──────────────────────────
        // Le job choisit Sirene (INSEE, FR) ou VIES (UE) selon le pays.
        // La company reste en 'pending_review' jusqu'à validation.
        // Non bloquant — l'inscription est confirmée immédiatement.
        VerifyCompanyJob::dispatch($result['company']->id);

        return response()->json([
            'message' => 'Demande de compte client créée avec succès.',
            'user' => [
                'id'                    => $result['user']->id,
                'name'                  => $result['user']->name,
                'email'                 => $result['user']->email,
                'role'                  => $result['user']->role,
                'pennylane_customer_id' => $result['user']->pennylane_customer_id,
            ],
            'company' => [
                'id'     => $result['company']->id,
                'name'   => $result['company']->name,
                'status' => $result['company']->status,
            ],
        ], 201);
    }

    // =========================================================================
    // CONNEXION
    // =========================================================================

    /**
     * Authentifie un client B2B et retourne un token Sanctum.
     *
     * VÉRIFIE :
     *   - email + password valides
     *   - role = 'client' (pas un admin qui essaie de se connecter ici)
     *
     * RETOURNE :
     *   token Bearer à stocker dans le cookie client_token_js côté SvelteKit
     */
    public function login(LoginClientRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::with('company')
            ->where('email', $data['email'])
            ->where('role', 'client')
            ->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        $token = $user->createToken('client-portal')->plainTextToken;

        return response()->json([
            'message'    => 'Connexion réussie.',
            'token'      => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id'                => $user->id,
                'name'              => $user->name,
                'email'             => $user->email,
                'role'              => $user->role,
                'is_active'         => $user->is_active,
                'email_verified_at' => $user->email_verified_at,
            ],
            'company' => $user->company ? [
                'id'        => $user->company->id,
                'name'      => $user->company->name,
                'status'    => $user->company->status,
                'is_active' => $user->company->is_active,
            ] : null,
        ]);
    }

    // =========================================================================
    // DÉCONNEXION
    // =========================================================================

    /**
     * Révoque le token Sanctum courant.
     *
     * NOTE : On ne vide PAS le panier BDD — le client le retrouve à la reconnexion.
     * Le cookie client_token_js est supprimé côté SvelteKit par deconnexion/+page.server.ts.
     */
    public function logout(): JsonResponse
    {
        $user = request()->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    // =========================================================================
    // MOT DE PASSE OUBLIÉ / RÉINITIALISATION
    // Logique mutualisée dans le trait HandlesPasswordReset (broker 'users').
    // =========================================================================

    /**
     * Envoie un email de réinitialisation de mot de passe.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // Délègue au trait partagé — broker 'users' par défaut.
        return $this->sendResetLink($request->only('email'));
    }

    /**
     * Réinitialise le mot de passe via le token reçu par email.
     * La révocation des tokens Sanctum est gérée dans le trait.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        // Délègue au trait partagé.
        return $this->resetUserPassword(
            $request->only('email', 'password', 'password_confirmation', 'token')
        );
    }
}