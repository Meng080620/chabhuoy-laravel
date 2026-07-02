<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeliveryManRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->isDeliveryMan(), 403, 'Delivery-man access required.');
        abort_unless($user->deliveryMan?->isActive() === true, 403, 'Delivery-man account is not active.');

        return $next($request);
    }
}
