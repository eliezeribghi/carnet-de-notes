<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->isClient()) {
            return response()->json([
                'message' => 'Accès réservé aux clients.'
            ], 403);
        }

        if (!$user->is_active) {
            return response()->json([
                'message' => 'Compte client inactif.'
            ], 403);
        }

        return $next($request);
    }
}
