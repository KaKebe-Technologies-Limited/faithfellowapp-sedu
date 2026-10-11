<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('login.id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6']]);

        $key = '2fa:'.$request->session()->get('login.id').'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => __('Too many attempts. Please log in again.')]);
        }

        $user = User::find($request->session()->get('login.id'));

        if (! $user || ! $user->two_factor_code || $user->two_factor_expires_at?->isPast()
            || ! Hash::check($request->input('code'), $user->two_factor_code)) {
            RateLimiter::hit($key, 600);
            throw ValidationException::withMessages(['code' => __('That code is invalid or has expired.')]);
        }

        RateLimiter::clear($key);
        $user->forceFill(['two_factor_code' => null, 'two_factor_expires_at' => null])->save();

        Auth::login($user, (bool) $request->session()->pull('login.remember'));
        $request->session()->forget('login.id');

        return AuthenticatedSessionController::completeLogin($request, $user);
    }
}
