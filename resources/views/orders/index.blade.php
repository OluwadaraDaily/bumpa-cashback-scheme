@extends('layouts.public')

@section('content')
    <section class="section-heading">
        <div>
            <p class="eyebrow">Order history</p>
            <h1>Your orders.</h1>
            <p>Review the purchases you have made with your account.</p>
        </div>
        <a class="button button-secondary" href="{{ route('shop') }}">Shop products</a>
    </section>

    @if ($orders->isEmpty())
        <section class="empty-state">
            <h2>No orders yet</h2>
            <p>Your completed purchases will appear here.</p>
            <a class="button" href="{{ route('shop') }}">Browse products</a>
        </section>
    @else
        <section class="order-list">
            @foreach ($orders as $order)
                <article class="order-card">
                    <div class="order-card-head">
                        <div>
                            <p class="product-label">Order #{{ $order->id }}</p>
                            <p class="muted">
                                {{ $order->created_at?->format('M j, Y \a\t g:i A') ?? 'Date unavailable' }}
                            </p>
                        </div>
                        <span class="order-status">{{ ucfirst($order->status) }}</span>
                    </div>
                    <div class="order-card-foot">
                        <span>{{ $order->items->sum('quantity') }} item{{ $order->items->sum('quantity') === 1 ? '' : 's' }}</span>
                        <strong>₦{{ number_format($order->total / 100, 2) }}</strong>
                        <a href="{{ route('orders.show', $order) }}">View details</a>
                    </div>
                </article>
            @endforeach
        </section>

        {{ $orders->links() }}
    @endif
@endsection
