<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendorRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->isVendor(), 403, 'Vendor access required.');
        abort_unless($user->vendor?->isActive() === true, 403, 'Vendor account is not active.');

        return $next($request);
    }
}
