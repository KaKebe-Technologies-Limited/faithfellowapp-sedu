<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Turns verified Google ID-token claims into a FaithFellows member: finds the existing account or
 * creates one, with the safety rules that make "Sign in with Google" safe. Every caller (the API
 * token endpoint and the hosted-page flow) goes through here so the rules cannot drift apart.
 */
class GoogleAccounts
{
    /**
     * @param  array<string, mixed>  $claims  Verified claims from GoogleIdTokenVerifier.
     * @return array{0: User, 1: bool} The member, and whether the account was just created.
     */
    public function resolve(array $claims): array
    {
        $email = Str::lower(trim($claims['email']));
        $googleId = (string) $claims['sub'];

        // A closed account keeps its email reserved; don't silently create a second one.
        $user = User::withTrashed()->where('google_id', $googleId)->first() ?? User::withTrashed()->where('email', $email)->first();
        abort_if($user?->trashed(), 403, __('This account has been closed. Please contact support.'));
        // An email address can pass to a different Google account (recycled addresses); never hand over a linked account.
        abort_if($user && $user->google_id && $user->google_id !== $googleId, 403, __('This email is linked to a different Google account.'));

        if (! $user) {
            $user = DB::transaction(function () use ($claims, $email, $googleId) {
                $user = new User([
                    'name' => Str::limit(trim((string) ($claims['name'] ?? '')) ?: Str::before($email, '@'), 255, ''),
                    'email' => $email,
                    // Nobody knows this password; the member signs in with Google (or resets it by email).
                    'password' => Str::random(40),
                    'country' => 'UG',
                    'language' => app()->getLocale(),
                    'terms_accepted_at' => now(),
                ]);
                // Google has proven the address, so it is verified from day one.
                $user->forceFill(['google_id' => $googleId, 'email_verified_at' => now()])->save();
                $user->assignRole(Role::findOrCreate('member', 'web'));

                return $user;
            });
            event(new Registered($user));

            return [$user, true];
        }

        $this->assertMayUseApp($user);

        // If the email was never verified, someone else may have registered it with a password they know.
        // Google just proved who owns it, so cut off any such password and old tokens before linking.
        if (! $user->email_verified_at) {
            $user->password = Str::random(40);
            $user->tokens()->delete();
        }
        $user->forceFill([
            'google_id' => $user->google_id ?: $googleId,
            'email_verified_at' => $user->email_verified_at ?: now(),
        ])->save();

        return [$user, false];
    }

    /** Suspended members and platform staff never get app tokens. */
    public function assertMayUseApp(User $user): void
    {
        abort_if($user->status !== 'active', 403, __('This account is suspended.'));
        abort_if($user->isSuperAdmin(), 403, __('Super admin accounts cannot use API tokens.'));
    }
}
