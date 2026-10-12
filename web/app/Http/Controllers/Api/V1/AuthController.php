<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\GoogleIdTokenVerifier;
use App\Support\Audit;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Personal access tokens for the mobile app and integrations (Sanctum).
 */
class AuthController extends Controller
{
    public function token(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $key = 'api-login:'.strtolower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key), 'minutes' => ceil(RateLimiter::availableIn($key) / 60)])]);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }
        RateLimiter::clear($key);

        abort_if($user->status !== 'active', 403, __('This account is suspended.'));
        // Platform staff never get long-lived API tokens.
        abort_if($user->isSuperAdmin(), 403, __('Super admin accounts cannot use API tokens.'));

        return $this->issueToken($request, $user, $data['device_name'], 'api.token_created');
    }

    /**
     * "Continue with Google": sign in, or create the member account on first use.
     * The app sends the Google ID token; we verify it (GoogleIdTokenVerifier) and never see a password.
     */
    public function google(Request $request, GoogleIdTokenVerifier $google): JsonResponse
    {
        $data = $request->validate([
            'credential' => ['required', 'string', 'max:4096'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        abort_unless($google->enabled(), 503, __('Google sign-in is not available right now.'));
        $claims = $google->verify($data['credential']);
        abort_unless($claims, 401, __('We could not verify your Google sign-in. Please try again.'));

        $email = Str::lower(trim($claims['email']));
        $googleId = (string) $claims['sub'];

        // A closed account keeps its email reserved; don't silently create a second one.
        $user = User::withTrashed()->where('google_id', $googleId)->first() ?? User::withTrashed()->where('email', $email)->first();
        abort_if($user?->trashed(), 403, __('This account has been closed. Please contact support.'));
        // An email address can pass to a different Google account (recycled addresses); never hand over a linked account.
        abort_if($user && $user->google_id && $user->google_id !== $googleId, 403, __('This email is linked to a different Google account.'));

        $created = ! $user;
        if ($created) {
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
        } else {
            abort_if($user->status !== 'active', 403, __('This account is suspended.'));
            // Platform staff never get long-lived API tokens.
            abort_if($user->isSuperAdmin(), 403, __('Super admin accounts cannot use API tokens.'));

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
        }

        return $this->issueToken($request, $user, $data['device_name'], $created ? 'api.google_registered' : 'api.google_signin', ['is_new_user' => $created]);
    }

    /**
     * The 201 response sign-in and Google sign-in both return: a 180-day token and the user.
     *
     * @param  array<string, mixed>  $extra  Extra top-level fields for the response.
     */
    protected function issueToken(Request $request, User $user, string $device, string $auditAction, array $extra = []): JsonResponse
    {
        $token = $user->createToken($device, ['member'], now()->addDays(180));
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        Audit::log($auditAction, $user, $device);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
            'user' => new UserResource($user->load('subscribedChurches')),
            ...$extra,
        ], 201);
    }

    public function revoke(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => __('Signed out.')]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('subscribedChurches.media'));
    }
}
