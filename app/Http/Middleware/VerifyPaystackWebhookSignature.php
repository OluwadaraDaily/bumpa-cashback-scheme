<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPaystackWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.paystack.secret_key');
        $signature = (string) $request->header('x-paystack-signature');

        if ($secret === '' || $signature === '') {
            return response()->json(['message' => 'Invalid webhook signature.'], Response::HTTP_UNAUTHORIZED);
        }

        $expectedSignature = hash_hmac('sha512', $request->getContent(), $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            return response()->json(['message' => 'Invalid webhook signature.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
