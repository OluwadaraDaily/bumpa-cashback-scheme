@extends('layouts.public')

@section('content')
    <section class="section-heading">
        <div>
            <p class="eyebrow">Checkout</p>
            <h1>Confirm your order.</h1>
            <p>Review the items and place your order when everything looks right.</p>
        </div>
        <a class="button button-secondary" href="{{ route('cart.index') }}">Back to cart</a>
    </section>

    @if ($errors->any())
        <div class="feedback feedback-error" role="alert">
            {{ $errors->first('checkout') ?: $errors->first('items') ?: $errors->first() }}
        </div>
    @endif

    <section class="checkout-layout">
        <div class="checkout-items">
            @foreach ($items as $item)
                <article class="checkout-item">
                    <div class="cart-item-mark">{{ strtoupper(substr($item['product']->name, 0, 1)) }}</div>
                    <div>
                        <p class="product-label">Product {{ str_pad((string) $item['product']->id, 2, '0', STR_PAD_LEFT) }}</p>
                        <h2>{{ $item['product']->name }}</h2>
                        <p class="muted">{{ $item['quantity'] }} × ₦{{ number_format($item['product']->price / 100, 2) }}</p>
                    </div>
                    <strong>₦{{ number_format($item['line_total'] / 100, 2) }}</strong>
                </article>
            @endforeach
        </div>

        <aside class="checkout-summary">
            <p class="eyebrow">Order summary</p>
            <div class="summary-row">
                <span>Items</span>
                <span>{{ $items->sum('quantity') }}</span>
            </div>
            <div class="summary-row summary-total">
                <span>Total</span>
                <strong>₦{{ number_format($total / 100, 2) }}</strong>
            </div>
            <form action="{{ route('checkout.store') }}" method="POST">
                @csrf
                <button class="button button-full" type="submit">Place order</button>
            </form>
            <p class="summary-note">Stock is reserved only when the order is placed.</p>
        </aside>
    </section>
@endsection
