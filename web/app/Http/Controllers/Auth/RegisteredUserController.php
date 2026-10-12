<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(protected RegistrationService $registration) {}

    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * One email, one account, many churches — every new account is a member.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        Auth::login($this->registration->register($request));

        // Return to a church page / give link if the visitor came from one.
        return redirect()->intended(route('dashboard', absolute: false));
    }
}
