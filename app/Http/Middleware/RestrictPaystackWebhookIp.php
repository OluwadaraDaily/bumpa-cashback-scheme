<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictPaystackWebhookIp
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<int, string> $allowedIps */
        $allowedIps = config('services.paystack.webhook_ips', []);

        if ($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            return response()->json(['message' => 'Webhook source is not allowed.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
