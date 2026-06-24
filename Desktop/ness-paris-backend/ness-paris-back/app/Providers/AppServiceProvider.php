<?php

// =============================================================================
// app/Providers/AppServiceProvider.php
//
// RESPONSABILITÉ :
//   Configuration globale de l'application au démarrage.
//
// CONTIENT :
//   - ResetPassword::createUrlUsing() → redirige le lien de reset vers
//     le front SvelteKit au lieu de Blade (Laravel natif).
//
// POURQUOI ICI ?
//   Laravel génère par défaut un lien de reset qui pointe vers une route Blade.
//   Comme le front est en SvelteKit séparé, on doit surcharger cette URL
//   pour qu'elle pointe vers la bonne page SvelteKit.
//
// LIEN GÉNÉRÉ :
//   {FRONTEND_URL}/reset-password?token=xxx&email=yyy
//   Ex: http://localhost:5173/reset-password?token=abc123&email=user@example.com
// =============================================================================

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ── URL de reset password → front SvelteKit ───────────────────────────
        // Par défaut Laravel pointe vers /reset-password (route Blade).
        // On surcharge pour pointer vers le front SvelteKit.
        //
        // Le front récupère token + email depuis l'URL et les envoie à :
        //   POST /api/client/reset-password/email
        //   ou
        //   POST /api/backoffice/reset-password/email
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));

            return $frontendUrl
                . '/reset-password'
                . '?token=' . $token
                . '&email=' . urlencode($user->email);
        });
    }
}
