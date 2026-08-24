@extends('layouts.public')

@section('content')
    <section class="section-heading">
        <div>
            <p class="eyebrow">Product catalogue</p>
            <h1>Find something worth celebrating.</h1>
            <p>Choose from the available products. Cart and checkout are coming next.</p>
        </div>
        <span class="count-pill">{{ $products->count() }} products</span>
    </section>

    <section class="product-grid" aria-label="Products">
        @forelse ($products as $product)
            <article class="product-card">
                <div class="product-mark">{{ strtoupper(substr($product->name, 0, 1)) }}</div>
                <div class="product-details">
                    <p class="product-label">Product {{ str_pad((string) $product->id, 2, '0', STR_PAD_LEFT) }}</p>
                    <h2>{{ $product->name }}</h2>
                    <p class="product-price">₦{{ number_format($product->price / 100, 2) }}</p>
                </div>
                <div class="product-stock {{ $product->quantity > 0 ? 'stock-available' : 'stock-empty' }}">
                    {{ $product->quantity > 0 ? $product->quantity.' in stock' : 'Out of stock' }}
                </div>
            </article>
        @empty
            <div class="empty-state">
                <h2>No products yet</h2>
                <p>Products will appear here when they are added.</p>
            </div>
        @endforelse
    </section>
@endsection
