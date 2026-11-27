<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea el portal de clientes mientras esté desactivado (fase futura).
 */
class EnsureClientPortalEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Features::clientPortal(), 404);

        return $next($request);
    }
}
