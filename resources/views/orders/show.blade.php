@extends('layouts.public')

@section('content')
    <section class="section-heading">
        <div>
            <p class="eyebrow">Order details</p>
            <h1>Order #{{ $order->id }}</h1>
            <p>
                {{ $order->created_at?->format('M j, Y \a\t g:i A') ?? 'Date unavailable' }}
                · {{ ucfirst($order->status) }}
            </p>
        </div>
        <a class="button button-secondary" href="{{ route('orders.index') }}">Back to orders</a>
    </section>

    <section class="order-detail-layout">
        <div class="order-detail-items">
            @foreach ($order->items as $item)
                <article class="order-detail-item">
                    <div class="cart-item-mark">{{ strtoupper(substr($item->product_name, 0, 1)) }}</div>
                    <div>
                        <p class="product-label">Purchased product</p>
                        <h2>{{ $item->product_name }}</h2>
                        <p class="muted">
                            {{ $item->quantity }} × ₦{{ number_format($item->unit_price / 100, 2) }}
                        </p>
                    </div>
                    <strong>₦{{ number_format($item->line_total / 100, 2) }}</strong>
                </article>
            @endforeach
        </div>

        <aside class="order-detail-summary">
            <p class="eyebrow">Summary</p>
            <div class="summary-row">
                <span>Status</span>
                <span>{{ ucfirst($order->status) }}</span>
            </div>
            <div class="summary-row">
                <span>Items</span>
                <span>{{ $order->items->sum('quantity') }}</span>
            </div>
            <div class="summary-row summary-total">
                <span>Total</span>
                <strong>₦{{ number_format($order->total / 100, 2) }}</strong>
            </div>
        </aside>
    </section>
@endsection
