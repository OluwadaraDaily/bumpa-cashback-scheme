<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Bumpa Cashback' }}</title>
    <link rel="stylesheet" href="{{ asset('css/web.css') }}">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="{{ route('home') }}">Bumpa Cashback</a>
        <nav class="site-nav">
            <a href="{{ route('shop') }}">Shop</a>
            @auth
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
</body>
</html>
