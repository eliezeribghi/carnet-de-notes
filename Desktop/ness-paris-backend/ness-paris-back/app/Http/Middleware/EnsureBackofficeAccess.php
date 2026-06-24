<?php

// =============================================================================
// app/Http/Middleware/EnsureBackofficeAccess.php
//
// RESPONSABILITÉ :
//   Vérifie qu'un utilisateur connecté est bien un admin ou employee actif.
//   Utilisé pour protéger toutes les routes du backoffice.
//
// ALIAS : 'backoffice.portal' (enregistré dans bootstrap/app.php)
//
// APPELÉ PAR :
//   Route::middleware(['auth:sanctum', 'backoffice.portal'])
// =============================================================================

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBackofficeAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Pas connecté
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        // Rôle non autorisé (client qui essaie d'accéder au backoffice)
        if (!in_array($user->role, ['admin', 'employee'])) {
            return response()->json(['error' => 'Accès refusé.'], 403);
        }

        // Compte désactivé
        if (!$user->is_active) {
            return response()->json(['error' => 'Compte désactivé.'], 403);
        }

        return $next($request);
    }
}
