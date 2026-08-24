<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\IdempotencyKeyConflict;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    public function show(CartService $cart): View|RedirectResponse
    {
        $items = $cart->items();

        if ($items->isEmpty()) {
            return redirect()
                ->route('cart.index')
                ->with('status', 'Add an item to your cart before checking out.');
        }

        $this->checkoutKey();

        return view('checkout.index', [
            'items' => $items,
            'total' => $cart->total(),
        ]);
    }

    public function store(
        Request $request,
        CartService $cart,
        OrderService $orders,
    ): RedirectResponse {
        $items = $cart->items();

        if ($items->isEmpty()) {
            return redirect()
                ->route('cart.index')
                ->with('status', 'Add an item to your cart before checking out.');
        }

        $orderItems = $items
            ->map(fn (array $item): array => [
                'product_id' => $item['product']->id,
                'quantity' => $item['quantity'],
            ])
            ->all();

        try {
            $result = $orders->create(
                $request->user(),
                $orderItems,
                $this->checkoutKey(),
            );
        } catch (IdempotencyKeyConflict $exception) {
            return back()->withErrors([
                'checkout' => $exception->getMessage(),
            ]);
        }

        $cart->clear();

        return redirect()->route('checkout.confirmation', $result->order);
    }

    public function confirmation(Request $request, Order $order): View
    {
        abort_unless($order->user_id === $request->user()->id, Response::HTTP_NOT_FOUND);

        return view('checkout.confirmation', [
            'order' => $order->load('items'),
        ]);
    }

    private function checkoutKey(): string
    {
        $key = session('checkout_key');

        if (! is_string($key) || $key === '') {
            $key = Str::uuid()->toString();
            session()->put('checkout_key', $key);
        }

        return $key;
    }
}
