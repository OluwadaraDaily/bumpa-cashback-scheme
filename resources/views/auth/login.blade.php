@extends('layouts.public')

@section('content')
    <section class="auth-card" data-auth-page>
        <div class="auth-heading">
            <p class="eyebrow">Welcome back</p>
            <h1>Log in to your account</h1>
            <p>Continue shopping and keep building your rewards.</p>
        </div>

        @if (session('status'))
            <div class="feedback feedback-success" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="feedback feedback-error" role="alert">
                <ul class="error-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('web.login') }}" method="POST">
            @csrf
            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>

            <button class="button button-full" type="submit">Log in</button>
        </form>

        <p class="auth-footer">New here? <a href="{{ route('signup') }}">Create an account</a></p>
    </section>
@endsection
