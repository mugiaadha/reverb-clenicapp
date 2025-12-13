<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * Add project-specific endpoints here if you need to bypass CSRF
     * protection for webhook endpoints or external callbacks.
     *
     * @var array<int, string>
     */
    protected $except = [
        'socket/*',
        'apps/*/events',
    ];
}
