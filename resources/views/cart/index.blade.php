@extends('layouts.public')

@section('content')
    <section class="section-heading">
        <div>
            <p class="eyebrow">Your cart</p>
            <h1>Review your items.</h1>
            <p>Update quantities or remove anything you no longer need.</p>
        </div>
        <a class="button button-secondary" href="{{ route('shop') }}">Continue shopping</a>
    </section>

    @if (session('status'))
        <div class="feedback feedback-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->has('cart'))
        <div class="feedback feedback-error" role="alert">
            {{ $errors->first('cart') }}
        </div>
    @endif

    @if ($items->isEmpty())
        <section class="empty-state">
            <h2>Your cart is empty</h2>
            <p>Choose a product to begin your purchase.</p>
            <a class="button" href="{{ route('shop') }}">Browse products</a>
        </section>
    @else
        <section class="cart-layout">
            <div class="cart-items">
                @foreach ($items as $item)
                    <article class="cart-item">
                        <div class="cart-item-mark">{{ strtoupper(substr($item['product']->name, 0, 1)) }}</div>
                        <div class="cart-item-details">
                            <p class="product-label">Product {{ str_pad((string) $item['product']->id, 2, '0', STR_PAD_LEFT) }}</p>
                            <h2>{{ $item['product']->name }}</h2>
                            <p class="muted">₦{{ number_format($item['product']->price / 100, 2) }} each</p>
                        </div>
                        <form class="quantity-form" action="{{ route('cart.items.update', $item['product']) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <label for="quantity-{{ $item['product']->id }}">Quantity</label>
                            <input id="quantity-{{ $item['product']->id }}" name="quantity" type="number" min="1" max="{{ $item['product']->quantity }}" value="{{ $item['quantity'] }}">
                            <button class="text-button" type="submit">Update</button>
                        </form>
                        <div class="cart-item-total">
                            <strong>₦{{ number_format($item['line_total'] / 100, 2) }}</strong>
                            <form action="{{ route('cart.items.destroy', $item['product']) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <button class="text-button danger-button" type="submit">Remove</button>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>

            <aside class="cart-summary">
                <p class="eyebrow">Summary</p>
                <div class="summary-row">
                    <span>Items</span>
                    <span>{{ $items->sum('quantity') }}</span>
                </div>
                <div class="summary-row summary-total">
                    <span>Total</span>
                    <strong>₦{{ number_format($total / 100, 2) }}</strong>
                </div>
                <p class="summary-note">Checkout will be connected in the next step.</p>
            </aside>
        </section>
    @endif
@endsection
