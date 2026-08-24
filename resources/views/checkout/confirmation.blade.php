@extends('layouts.public')

@section('content')
    <section class="confirmation-card">
        <div class="confirmation-icon">✓</div>
        <p class="eyebrow">Order complete</p>
        <h1>Thanks for your purchase.</h1>
        <p>Your order #{{ $order->id }} was placed successfully.</p>
        <p class="confirmation-total">₦{{ number_format($order->total / 100, 2) }}</p>

        <div class="confirmation-items">
            @foreach ($order->items as $item)
                <div>
                    <span>{{ $item->product_name }} × {{ $item->quantity }}</span>
                    <strong>₦{{ number_format($item->line_total / 100, 2) }}</strong>
                </div>
            @endforeach
        </div>

        <div class="hero-actions">
            <a class="button" href="{{ route('orders.show', $order) }}">View order</a>
            <a class="button" href="{{ route('shop') }}">Continue shopping</a>
            <a class="button button-secondary" href="{{ route('home') }}">Go home</a>
        </div>
    </section>
@endsection
