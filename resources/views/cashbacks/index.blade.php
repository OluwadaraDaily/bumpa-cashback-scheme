@extends('layouts.public')

@section('content')
    <section class="section-heading">
        <div>
            <p class="eyebrow">Cashback</p>
            <h1>Your cashback.</h1>
            <p>Each unlocked badge earns you ₦300.00 cashback.</p>
        </div>
        <a class="button button-secondary" href="{{ route('achievements.index') }}">View progress</a>
    </section>

    <section class="cashback-summary">
        <div>
            <p class="eyebrow">Total cashback</p>
            <h2>₦{{ number_format($totalAmount / 100, 2) }}</h2>
        </div>
        <div>
            <p class="eyebrow">Paid to you</p>
            <h2>₦{{ number_format($paidAmount / 100, 2) }}</h2>
        </div>
    </section>

    @if ($cashbacks->isEmpty())
        <section class="empty-state">
            <h2>No cashback yet</h2>
            <p>Unlock a badge to earn your first ₦300.00 cashback.</p>
            <a class="button" href="{{ route('achievements.index') }}">View badges</a>
        </section>
    @else
        <section class="cashback-list">
            @foreach ($cashbacks as $cashback)
                <article class="cashback-card">
                    <div class="cashback-card-head">
                        <div>
                            <p class="product-label">Badge cashback</p>
                            <h2>{{ $cashback->badge->name }}</h2>
                            <p class="muted">{{ $cashback->description }}</p>
                        </div>
                        <span class="cashback-status cashback-status-{{ $cashback->status->value }}">
                            {{ ucfirst($cashback->status->value) }}
                        </span>
                    </div>
                    <div class="cashback-card-foot">
                        <strong>₦{{ number_format($cashback->amount / 100, 2) }}</strong>
                        <span class="muted">
                            Earned {{ $cashback->created_at?->format('M j, Y') ?? 'Date unavailable' }}
                        </span>
                        <a href="{{ route('cashbacks.show', $cashback) }}">View details</a>
                    </div>
                </article>
            @endforeach
        </section>

        {{ $cashbacks->links() }}
    @endif
@endsection
