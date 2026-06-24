<?php

// =============================================================================
// app/Http/Controllers/Concerns/HandlesPasswordReset.php
//
// RESPONSABILITÉ :
//   Logique partagée de réinitialisation de mot de passe PAR EMAIL.
//   Mutualise le code entre ClientAuthController (clients B2B) et
//   BackofficeAuthController (admin / employee).
//
// POURQUOI UN TRAIT ?
//   Le flux "mot de passe oublié" est strictement identique des deux côtés :
//     1. Envoi du lien de reset  → Password::sendResetLink()
//     2. Réinitialisation token  → Password::reset() + révocation des tokens
//   Seul le BROKER change (passé en paramètre). On évite ainsi de dupliquer
//   la même dizaine de lignes — et les mêmes bugs potentiels — dans chaque
//   contrôleur. Une seule source de vérité.
//
// BROKER :
//   Défaut 'users' (table password_reset_tokens, provider users).
//   Clients ET comptes backoffice vivent dans la même table `users`, donc
//   le même broker convient. Le seul élément réellement spécifique — l'URL
//   du lien — est géré dans la notification (voir étape 4), pas ici.
// =============================================================================

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

trait HandlesPasswordReset
{
    /**
     * Envoie l'email contenant le lien de réinitialisation.
     *
     * ÉTAPES :
     *   1. Le broker génère un token et le stocke dans password_reset_tokens
     *   2. La notification (User::sendPasswordResetNotification) part par mail
     *
     * RÉPONSE :
     *   200 si le lien est envoyé, 422 sinon (email inconnu, throttle…).
     *   Le message est traduit via lang/fr/passwords.php.
     *
     * @param array  $credentials  ['email' => '...']
     * @param string $broker       Broker à utiliser (défaut : 'users')
     */
    protected function sendResetLink(array $credentials, string $broker = 'users'): JsonResponse
    {
        $status = Password::broker($broker)->sendResetLink($credentials);

        return response()->json(
            ['message' => __($status)],
            $status === Password::RESET_LINK_SENT ? 200 : 422
        );
    }

    /**
     * Réinitialise le mot de passe à partir du token reçu par email.
     *
     * APRÈS RESET :
     *   - Nouveau hash + remember_token régénéré
     *   - TOUS les tokens Sanctum révoqués (sécurité → force la reconnexion)
     *   - Événement PasswordReset dispatché (listeners éventuels)
     *
     * RÉPONSE :
     *   200 si réinitialisé, 422 sinon (token invalide / expiré).
     *
     * @param array  $data    ['email', 'password', 'password_confirmation', 'token']
     * @param string $broker  Broker à utiliser (défaut : 'users')
     */
    protected function resetUserPassword(array $data, string $broker = 'users'): JsonResponse
    {
        $status = Password::broker($broker)->reset(
            $data,
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Révoque tous les tokens → force l'utilisateur à se reconnecter
                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        return response()->json(
            ['message' => __($status)],
            $status === Password::PASSWORD_RESET ? 200 : 422
        );
    }
}