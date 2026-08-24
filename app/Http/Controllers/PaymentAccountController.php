<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentAccountRequest;
use App\Http\Resources\PaymentAccountResource;
use App\Services\PaymentAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class PaymentAccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return PaymentAccountResource::collection(
            $request->user()->paymentAccounts()->orderBy('provider')->get()
        );
    }

    public function upsert(
        StorePaymentAccountRequest $request,
        string $provider,
        PaymentAccountService $accounts,
    ): JsonResponse {
        $account = $accounts->upsert(
            $request->user(),
            $provider,
            $request->validated(),
        );

        return (new PaymentAccountResource($account))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    public function destroy(
        Request $request,
        string $provider,
        PaymentAccountService $accounts,
    ): Response {
        $accounts->deactivate($request->user(), $provider);

        return response()->noContent();
    }
}
