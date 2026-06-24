<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientCompanyIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->company) {
            return response()->json([
                'message' => 'Aucune société associée à ce compte.'
            ], 403);
        }

        if (!$user->company->isApproved()) {
            return response()->json([
                'message' => 'Société non approuvée pour l’accès B2B.'
            ], 403);
        }

        return $next($request);
    }
}
