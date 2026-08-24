@extends('layouts.public')

@section('content')
    <section class="auth-card" data-auth-page>
        <div class="auth-heading">
            <p class="eyebrow">Start earning</p>
            <h1>Create your account</h1>
            <p>Buy products, unlock achievements, and earn cashback.</p>
        </div>

        @if ($errors->any())
            <div class="feedback feedback-error" role="alert">
                <ul class="error-list">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('web.signup') }}" method="POST">
            @csrf
            <label for="username">Username</label>
            <input id="username" name="username" type="text" autocomplete="username" value="{{ old('username') }}" required>

            <label for="email">Email</label>
            <input id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required>

            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>

            <button class="button button-full" type="submit">Create account</button>
        </form>

        <p class="auth-footer">Already registered? <a href="{{ route('login') }}">Log in</a></p>
    </section>
@endsection
