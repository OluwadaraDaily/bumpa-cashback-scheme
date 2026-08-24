<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\PaymentAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(
        LoginRequest $request,
        PaymentAccountService $accounts,
    ): RedirectResponse {
        $data = $request->validated();

        if (! Auth::attempt([
            'email' => $data['email'],
            'password' => $data['password'],
        ])) {
            return back()
                ->withErrors(['email' => 'The provided credentials are incorrect.'])
                ->withInput($request->only('email'));
        }

        $accounts->assignDefault($request->user());

        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function register(
        RegisterRequest $request,
        PaymentAccountService $accounts,
    ): RedirectResponse {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['username'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);
        $accounts->assignDefault($user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You have been logged out.');
    }
}
