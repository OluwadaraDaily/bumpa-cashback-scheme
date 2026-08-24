<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Bumpa Cashback' }}</title>
    <link rel="stylesheet" href="{{ asset('css/web.css') }}">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="{{ route('home') }}">Bumpa Cashback</a>
        <nav class="site-nav">
            <a href="{{ route('shop') }}">Shop</a>
            <a href="{{ route('cart.index') }}">Cart ({{ collect(session('cart', []))->sum() }})</a>
            @auth
                <a href="{{ route('orders.index') }}">Orders</a>
                <a href="{{ route('achievements.index') }}">Progress</a>
                <a href="{{ route('cashbacks.index') }}">Cashback</a>
                <span class="nav-user">Hi, {{ auth()->user()->username }}</span>
                <form action="{{ route('web.logout') }}" method="POST">
                    @csrf
                    <button class="button button-small button-secondary" type="submit">Log out</button>
                </form>
            @else
                <a href="{{ route('login') }}">Log in</a>
                <a class="button button-small" href="{{ route('signup') }}">Create account</a>
            @endauth
        </nav>
    </header>

    <main class="page-shell">
        @yield('content')
    </main>

    @auth
        <div
            id="achievement-notifications"
            class="notification-stack"
            data-index-url="{{ route('notifications.index') }}"
            data-read-url="{{ route('notifications.read', ['notification' => '__NOTIFICATION__']) }}"
            data-poll="{{ request()->routeIs('checkout.confirmation') ? 'true' : 'false' }}"
            aria-live="polite"
            aria-label="Achievement notifications"
        ></div>
        <script src="{{ asset('js/achievement-notifications.js') }}" defer></script>
    @endauth
</body>
</html>
