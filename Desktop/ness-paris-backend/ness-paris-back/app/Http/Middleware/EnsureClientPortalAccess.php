<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientPortalAccess
{
public function handle(Request $request, Closure $next): Response
{
    $user = $request->user();

    if (! $user) {
        abort(401, 'Unauthenticated.');
    }

    if ($user->is_admin) {
        abort(403, 'Admins cannot access client portal.');
    }

    if (! $user->is_active) {
        abort(403, 'Inactive account.');
    }

    if (! $user->email_verified_at) {
        abort(403, 'Email not verified.');
    }


    // Un client avec société pending_review ou rejected peut se connecter.
    // Il voit les prix publics. Seul approved donne accès aux prix pro.
    // La logique de pricing est gérée par User::canAccessProPricing().

    return $next($request);
}
}
