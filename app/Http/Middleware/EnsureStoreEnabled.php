<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea la tienda en línea mientras esté desactivada (fase futura).
 */
class EnsureStoreEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Features::store(), 404);

        return $next($request);
    }
}
