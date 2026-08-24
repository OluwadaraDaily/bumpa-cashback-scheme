<?php

namespace App\Http\Controllers;

use App\Services\Payments\PaystackWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, PaystackWebhookService $webhooks): JsonResponse
    {
        $webhooks->handle($request->all());

        return response()->json(['status' => 'ok']);
    }
}
