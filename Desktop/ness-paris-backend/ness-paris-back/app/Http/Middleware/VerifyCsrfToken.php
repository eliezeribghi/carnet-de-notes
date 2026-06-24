<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Les URIs exemptées de vérification CSRF.
     *
     * @var array<int, string>
     */
    protected $except = [
        'api/*',  // ✅ Toutes les routes /api/* sans CSRF
    ];
}

