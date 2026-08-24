<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\AddCartItemRequest;
use App\Http\Requests\Web\UpdateCartItemRequest;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CartController extends Controller
{
    public function index(CartService $cart): View
    {
        return view('cart.index', [
            'items' => $cart->items(),
            'total' => $cart->total(),
        ]);
    }

    public function store(AddCartItemRequest $request, CartService $cart): RedirectResponse
    {
        $product = Product::query()->findOrFail($request->validated('product_id'));
        $cart->add($product, (int) $request->validated('quantity'));

        return redirect()
            ->route('cart.index')
            ->with('status', "{$product->name} was added to your cart.");
    }

    public function update(
        UpdateCartItemRequest $request,
        Product $product,
        CartService $cart,
    ): RedirectResponse {
        $cart->update($product, (int) $request->validated('quantity'));

        return redirect()->route('cart.index');
    }

    public function destroy(Product $product, CartService $cart): RedirectResponse
    {
        $cart->remove($product);

        return redirect()->route('cart.index')->with('status', 'Item removed from your cart.');
    }
}
