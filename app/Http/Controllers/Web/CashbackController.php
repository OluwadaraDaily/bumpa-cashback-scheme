<?php

namespace App\Http\Controllers\Web;

use App\Enums\CashbackStatus;
use App\Http\Controllers\Controller;
use App\Models\Cashback;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CashbackController extends Controller
{
    public function index(Request $request): View
    {
        $cashbackQuery = Cashback::query()->where('user_id', $request->user()->id);

        return view('cashbacks.index', [
            'cashbacks' => (clone $cashbackQuery)
                ->with('badge')
                ->latest('id')
                ->paginate(10),
            'totalAmount' => (clone $cashbackQuery)->sum('amount'),
            'paidAmount' => (clone $cashbackQuery)
                ->where('status', CashbackStatus::PAID->value)
                ->sum('amount'),
        ]);
    }

    public function show(Request $request, Cashback $cashback): View
    {
        abort_unless($cashback->user_id === $request->user()->id, Response::HTTP_NOT_FOUND);

        return view('cashbacks.show', [
            'cashback' => $cashback->load([
                'badge',
                'paymentAttempts' => fn ($query) => $query->latest('id'),
            ]),
        ]);
    }
}
