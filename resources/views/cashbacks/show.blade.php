@extends('layouts.public')

@section('content')
    <section class="section-heading">
        <div>
            <p class="eyebrow">Cashback details</p>
            <h1>{{ $cashback->badge->name }} cashback.</h1>
            <p>{{ $cashback->description }}</p>
        </div>
        <a class="button button-secondary" href="{{ route('cashbacks.index') }}">Back to cashback</a>
    </section>

    <section class="cashback-detail-layout">
        <article class="cashback-detail-card">
            <div class="cashback-detail-heading">
                <div>
                    <p class="product-label">Amount</p>
                    <h2>₦{{ number_format($cashback->amount / 100, 2) }}</h2>
                </div>
                <span class="cashback-status cashback-status-{{ $cashback->status->value }}">
                    {{ ucfirst($cashback->status->value) }}
                </span>
            </div>

            <div class="cashback-detail-rows">
                <div>
                    <span>Badge</span>
                    <strong>{{ $cashback->badge->name }}</strong>
                </div>
                <div>
                    <span>Earned</span>
                    <strong>{{ $cashback->created_at?->format('M j, Y \a\t g:i A') ?? 'Date unavailable' }}</strong>
                </div>
                <div>
                    <span>Paid</span>
                    <strong>{{ $cashback->paid_at?->format('M j, Y \a\t g:i A') ?? 'Not paid yet' }}</strong>
                </div>
            </div>
        </article>

        <article class="cashback-detail-card">
            <p class="eyebrow">Payment attempts</p>
            @forelse ($cashback->paymentAttempts as $attempt)
                <div class="payment-attempt">
                    <div>
                        <strong>{{ ucfirst($attempt->status->value) }}</strong>
                        <p class="muted">{{ ucfirst($attempt->provider) }} · ₦{{ number_format($attempt->amount / 100, 2) }}</p>
                    </div>
                    <div class="payment-attempt-meta">
                        @if ($attempt->provider_transfer_reference)
                            <span>Reference: {{ $attempt->provider_transfer_reference }}</span>
                        @endif
                        @if ($attempt->failure_message)
                            <span class="payment-failure">{{ $attempt->failure_message }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="muted payment-empty">Payment processing has not started yet.</p>
            @endforelse
        </article>
    </section>
@endsection
