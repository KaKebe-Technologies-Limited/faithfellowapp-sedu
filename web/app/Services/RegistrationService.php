<?php

namespace App\Services;

use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Creates member accounts. Shared by the website sign-up form and the mobile API so both apply
 * exactly the same rules: one email, one account, every new account is a member.
 */
class RegistrationService
{
    public function register(RegisterRequest $request): User
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

        // Sends the "verify your email" message.
        event(new Registered($user));

        return $user;
    }
}
