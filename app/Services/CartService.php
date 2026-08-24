<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function add(Product $product, int $quantity): void
    {
        $cart = $this->cart();
        $newQuantity = ($cart[$product->id] ?? 0) + $quantity;

        $this->ensureStock($product, $newQuantity);

        $cart[$product->id] = $newQuantity;
        session()->put('cart', $cart);
        session()->forget('checkout_key');
    }

    public function update(Product $product, int $quantity): void
    {
        if (! array_key_exists($product->id, $this->cart())) {
            return;
        }

        $this->ensureStock($product, $quantity);

        $cart = $this->cart();
        $cart[$product->id] = $quantity;
        session()->put('cart', $cart);
        session()->forget('checkout_key');
    }

    public function remove(Product $product): void
    {
        $cart = $this->cart();
        unset($cart[$product->id]);
        session()->put('cart', $cart);
        session()->forget('checkout_key');
    }

    public function clear(): void
    {
        session()->forget(['cart', 'checkout_key']);
    }

    /**
     * @return Collection<int, array{product: Product, quantity: int, line_total: int}>
     */
    public function items(): Collection
    {
        $cart = $this->cart();

        if ($cart === []) {
            return collect();
        }

        $products = Product::query()
            ->whereIn('id', array_keys($cart))
            ->get()
            ->keyBy('id');

        return collect($cart)
            ->map(function (int $quantity, int|string $productId) use ($products): ?array {
                $product = $products->get((int) $productId);

                if (! $product) {
                    return null;
                }

                return [
                    'product' => $product,
                    'quantity' => $quantity,
                    'line_total' => $product->price * $quantity,
                ];
            })
            ->filter()
            ->values();
    }

    public function total(): int
    {
        return $this->items()->sum('line_total');
    }

    public function count(): int
    {
        return array_sum($this->cart());
    }

    /**
     * @return array<int|string, int>
     */
    private function cart(): array
    {
        return collect(session('cart', []))
            ->mapWithKeys(fn ($quantity, $productId): array => [(int) $productId => (int) $quantity])
            ->all();
    }

    private function ensureStock(Product $product, int $quantity): void
    {
        if ($quantity > $product->quantity) {
            throw ValidationException::withMessages([
                'cart' => ["There is not enough stock for {$product->name}."],
            ]);
        }
    }
}
