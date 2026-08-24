<?php

namespace App\Http\Controllers;

use App\Http\Resources\CashbackResource;
use App\Models\Cashback;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class CashbackController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $cashbacks = Cashback::query()
            ->where('user_id', $request->user()->id)
            ->with(['badge', 'paymentAttempts'])
            ->latest('id')
            ->paginate(20);

        return CashbackResource::collection($cashbacks);
    }

    public function show(Request $request, Cashback $cashback): CashbackResource
    {
        abort_unless($cashback->user_id === $request->user()->id, Response::HTTP_NOT_FOUND);

        return new CashbackResource(
            $cashback->load(['badge', 'paymentAttempts'])
        );
    }
}
