@extends('layouts.public')

@section('content')
    <section class="hero-card">
        @auth
            <p class="eyebrow">You are signed in</p>
            <h1>Welcome, {{ auth()->user()->username }}.</h1>
            <p class="hero-copy">
                Your shopping and rewards journey will appear here as we add the next screens.
            </p>
            <div class="hero-actions">
                <a class="button" href="{{ route('shop') }}">Browse products</a>
            </div>
        @else
            <p class="eyebrow">Shop. Achieve. Get rewarded.</p>
            <h1>Turn every purchase into progress.</h1>
            <p class="hero-copy">
                Create an account to buy products, unlock achievements, earn badges, and receive cashback.
            </p>
            <div class="hero-actions">
                <a class="button" href="{{ route('signup') }}">Create an account</a>
                <a class="button button-secondary" href="{{ route('login') }}">Log in</a>
            </div>
            <p class="session-note"><a href="{{ route('shop') }}">Browse the product catalogue first</a></p>
        @endauth
    </section>
@endsection
