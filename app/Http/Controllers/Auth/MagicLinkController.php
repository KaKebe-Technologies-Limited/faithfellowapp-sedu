<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\Auth\MagicLoginLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

/**
 * Passwordless "magic link" login — handy on shared phones where people
 * forget passwords. Links are signed and expire after 15 minutes.
 */
class MagicLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.magic-link');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', strtolower($request->input('email')))->first();

        if ($user && $user->isActive()) {
            $url = URL::temporarySignedRoute('magic-link.login', now()->addMinutes(15), [
                'user' => $user->id,
                'hash' => sha1($user->email.$user->password),
            ]);
            $user->notify(new MagicLoginLink($url));
        }

        // Same response either way — don't reveal which emails are registered.
        return back()->with('status', __('If that email is registered, a sign-in link is on its way. It expires in 15 minutes.'));
    }

    public function login(Request $request, User $user, string $hash): RedirectResponse
    {
        abort_unless(hash_equals(sha1($user->email.$user->password), $hash), 403);
        abort_unless($user->isActive(), 403);

        if ($user->two_factor_enabled) {
            return redirect()->route('login')->withErrors(['email' => __('Two-factor accounts must sign in with a password.')]);
        }

        Auth::login($user);
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return AuthenticatedSessionController::completeLogin($request, $user);
    }
}
