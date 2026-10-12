<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * One email, one account, many churches — every new account is a member.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = User::create([
            'name' => $request->string('name')->trim()->toString(),
            'email' => $request->string('email')->lower()->toString(),
            'phone' => $request->input('phone'),
            'country' => $request->input('country', 'UG'),
            'language' => $request->input('language', app()->getLocale()),
            'password' => Hash::make($request->input('password')),
            'email_consent' => $request->boolean('email_consent'),
            'terms_accepted_at' => now(),
        ]);

        $user->assignRole(Role::findOrCreate('member', 'web'));

        event(new Registered($user));

        Auth::login($user);

        // Return to a church page / give link if the visitor came from one.
        return redirect()->intended(route('dashboard', absolute: false));
    }
}
