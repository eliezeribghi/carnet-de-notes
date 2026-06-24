<?php
// =============================================================================
// app/Http/Controllers/Api/BackofficeAuthController.php
//
// RESPONSABILITÉ :
//   Authentification backoffice (admin / employee) :
//   connexion, déconnexion, mot de passe oublié / réinitialisation par email.
//
// DIFFÉRENCE AVEC ClientAuthController :
//   Ici on n'accepte QUE les rôles 'admin' et 'employee' (pas les clients B2B).
//   Pas de company associée, pas de flux d'inscription (les comptes internes
//   sont créés via UserController::store par un admin).
//
// RESET PASSWORD :
//   La logique est mutualisée dans le trait HandlesPasswordReset (broker 'users').
// =============================================================================

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\HandlesPasswordReset;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class BackofficeAuthController extends Controller
{
    use HandlesPasswordReset;

    // =========================================================================
    // CONNEXION
    // =========================================================================

    /**
     * Authentifie un compte backoffice et retourne un token Sanctum.
     *
     * VÉRIFIE :
     *   - email + password valides
     *   - role = 'admin' ou 'employee' (un client B2B ne se connecte pas ici)
     *   - compte actif (is_active)
     *
     * RETOURNE :
     *   token Bearer à stocker côté front backoffice.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        // On ne cherche QUE des comptes internes.
        $user = User::where('email', $request->email)
            ->whereIn('role', ['admin', 'employee'])
            ->first();

        // Message volontairement générique (pas d'indice email existe/n'existe pas).
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        // Compte désactivé → on bloque même si les identifiants sont bons.
        if (!$user->is_active) {
            return response()->json(['message' => 'Compte désactivé.'], 403);
        }

        $token = $user->createToken('backoffice-portal')->plainTextToken;

        return response()->json([
            'message'    => 'Connexion réussie.',
            'token'      => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'role'      => $user->role,
                'is_admin'  => $user->is_admin,
                'is_active' => $user->is_active,
            ],
        ]);
    }

    // =========================================================================
    // DÉCONNEXION
    // =========================================================================

    /**
     * Révoque le token Sanctum courant.
     * Protégé par auth:sanctum + backoffice.portal (voir routes/api.php).
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    // =========================================================================
    // MOT DE PASSE OUBLIÉ / RÉINITIALISATION PAR EMAIL
    // Logique mutualisée dans le trait HandlesPasswordReset (broker 'users').
    // =========================================================================

    /**
     * Envoie un email de réinitialisation aux comptes backoffice.
     *
     * NOTE SÉCURITÉ : le broker 'users' n'opère aucun filtrage par rôle.
     * Le lien envoyé pointe vers le bon front selon le rôle du compte
     * (voir ResetPasswordNotification). Si tu veux restreindre l'envoi aux
     * seuls admin/employee, il faudra un broker dédié ou un check préalable.
     */
    public function forgotPasswordByEmail(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        // Délègue au trait partagé.
        return $this->sendResetLink($request->only('email'));
    }

    /**
     * Réinitialise le mot de passe backoffice via le token reçu par email.
     * La révocation des tokens Sanctum est gérée dans le trait.
     */
    public function resetPasswordByEmail(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        // Délègue au trait partagé.
        return $this->resetUserPassword(
            $request->only('email', 'password', 'password_confirmation', 'token')
        );
    }
}