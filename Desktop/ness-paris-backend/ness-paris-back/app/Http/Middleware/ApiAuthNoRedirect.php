<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthNoRedirect
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
  public function handle(Request $request, Closure $next)
{
    if ($request->is('api/*')) {
        config(['sanctum.guard' => 'web']);
        config(['auth.defaults.guard' => 'sanctum']);
    }
    return $next($request);
}

}
