<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Auth\GoogleAppAuthController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\Auth\TwoFactorCode;
use App\Services\AccountDeletion;
use App\Services\Auth\GoogleAccounts;
use App\Services\Auth\GoogleIdTokenVerifier;
use App\Services\RegistrationService;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

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
        // An account that signs in with an email code on the website is asked for the code in the app too (a second step, POST /auth/two-factor),
        // so the app is never a way around it.
        if ($user->two_factor_enabled) {
            $this->sendCode($user);

            return response()->json(['two_factor' => true, 'message' => __('We sent a 6-digit code to :email. Enter it to finish signing in.', ['email' => $user->email])], 202);
        }

        return $this->issueToken($request, $user, $data['device_name'], $user->isSuperAdmin() ? 'api.super_admin_token_created' : 'api.token_created');
    }

    /** The second step for an account with the email code switched on: the six digits that were emailed, then the same token as a normal sign-in. */
    public function twoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $key = 'api-2fa:'.strtolower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => __('Too many attempts. Please sign in again to get a new code.')]);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->two_factor_enabled || ! $user->two_factor_code || $user->two_factor_expires_at?->isPast() || ! Hash::check($data['code'], $user->two_factor_code)) {
            RateLimiter::hit($key, 600);
            throw ValidationException::withMessages(['code' => __('That code is invalid or has expired.')]);
        }
        RateLimiter::clear($key);
        $user->forceFill(['two_factor_code' => null, 'two_factor_expires_at' => null])->save();
        abort_if($user->status !== 'active', 403, __('This account is suspended.'));

        return $this->issueToken($request, $user, $data['device_name'], $user->isSuperAdmin() ? 'api.super_admin_token_created' : 'api.token_created');
    }

    /** Emails a new six-digit code, valid for 10 minutes (signing in again within a minute does not send another). */
    protected function sendCode(User $user): void
    {
        if ($user->two_factor_expires_at?->gt(now()->addMinutes(9))) {
            return;
        }
        $code = (string) random_int(100000, 999999);
        $user->forceFill(['two_factor_code' => Hash::make($code), 'two_factor_expires_at' => now()->addMinutes(10)])->save();
        try {
            $user->notify(new TwoFactorCode($code));
        } catch (\Throwable $e) {
            // The email could not be sent: say so plainly, and do not leave a code nobody has.
            report($e);
            $user->forceFill(['two_factor_code' => null, 'two_factor_expires_at' => null])->save();
            abort(503, __('We could not email your code. Please try again in a moment.'));
        }
    }

    /**
     * Create a member account with email and password, and sign it in. Same rules as the website
     * form (RegisterRequest + RegistrationService); the device name labels the token.
     */
    public function register(RegisterRequest $request, RegistrationService $registration): JsonResponse
    {
        $device = $request->validate(['device_name' => ['required', 'string', 'max:100']])['device_name'];

        return $this->issueToken($request, $registration->register($request), $device, 'api.registered', ['is_new_user' => true]);
    }

    /**
     * "Continue with Google": sign in, or create the member account on first use.
     * The app sends the Google ID token; we verify it (GoogleIdTokenVerifier) and never see a password.
     */
    public function google(Request $request, GoogleIdTokenVerifier $google, GoogleAccounts $accounts): JsonResponse
    {
        $data = $request->validate([
            'credential' => ['required', 'string', 'max:4096'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        abort_unless($google->enabled(), 503, __('Google sign-in is not available right now.'));
        $claims = $google->verify($data['credential']);
        abort_unless($claims, 401, __('We could not verify your Google sign-in. Please try again.'));

        [$user, $created] = $accounts->resolve($claims);

        return $this->issueToken($request, $user, $data['device_name'], $created ? 'api.google_registered' : 'api.google_signin', ['is_new_user' => $created]);
    }

    /**
     * Last step of the hosted-page Google flow: trade the one-time code (from the app's return link)
     * plus the PKCE verifier only this app instance knows for a token. A code works once, for two minutes.
     */
    public function exchangeGoogleCode(Request $request, GoogleAccounts $accounts): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:40'],
            'verifier' => ['required', 'string', 'min:43', 'max:128'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $grant = Cache::pull(GoogleAppAuthController::CODE_PREFIX.hash('sha256', $data['code'])); // pull = single use
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $data['verifier'], true)), '+/', '-_'), '=');
        abort_unless($grant && hash_equals($grant['challenge'], $challenge), 401, __('This sign-in link has expired. Please try again.'));

        $user = User::findOrFail($grant['user_id']);
        $accounts->assertMayUseApp($user);

        return $this->issueToken($request, $user, $data['device_name'], $grant['created'] ? 'api.google_registered' : 'api.google_signin', ['is_new_user' => $grant['created']]);
    }

    /**
     * The 201 response sign-in and Google sign-in both return: a 180-day token and the user.
     *
     * @param  array<string, mixed>  $extra  Extra top-level fields for the response.
     */
    protected function issueToken(Request $request, User $user, string $device, string $auditAction, array $extra = []): JsonResponse
    {
        // A member stays signed in for 180 days; the platform admin for 7, so a lost phone does not hold the keys to the platform for long.
        $token = $user->createToken($device, ['member'], now()->addDays($user->isSuperAdmin() ? 7 : 180));
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

    /**
     * Delete the signed-in member's account from the app. The member confirms by typing their email address (Google sign-in accounts have
     * no password to ask for). Personal data is removed and gifts stay without a name; see AccountDeletion.
     */
    public function destroy(Request $request, AccountDeletion $deletion): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['email' => ['required', 'string']]);

        if (strcasecmp(trim($data['email']), $user->email) !== 0) {
            throw ValidationException::withMessages(['email' => __('That is not the email address of this account.')]);
        }
        abort_if(($reason = $deletion->blocker($user)) !== null, 403, (string) $reason);

        $deletion->delete($user);

        return response()->json(['message' => __('Your account has been deleted.')]);
    }
}
