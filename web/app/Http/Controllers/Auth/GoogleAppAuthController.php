<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\GoogleAccounts;
use App\Services\Auth\GoogleIdTokenVerifier;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * "Continue with Google" for the mobile app, through the system browser.
 *
 *   app ──opens──▶ /auth/google/app/start?redirect=<app link>&challenge=<PKCE>
 *        start ──▶ Google ──▶ /auth/google/app/callback?code&state
 *        callback: exchanges Google's code (server-side, with the client secret), verifies the ID token,
 *                  finds or creates the member, then sends the browser back to the app with a ONE-TIME
 *                  code (…?code=…). The app trades that for an API token at POST /api/v1/auth/google/exchange.
 *
 * Nothing secret travels through the app link: the code is single-use, expires in two minutes and
 * only works with the PKCE verifier held by the app that started the sign-in. The state is encrypted,
 * so no server session (and no cookie) is needed, which matters because Google redirects the browser back.
 */
class GoogleAppAuthController extends Controller
{
    /** Cache key prefix of the one-time codes (shared with the API exchange endpoint). */
    public const CODE_PREFIX = 'google-app-code:';

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function start(Request $request, GoogleIdTokenVerifier $verifier): RedirectResponse
    {
        $data = $request->validate([
            'redirect' => ['required', 'string', 'max:300'],
            'challenge' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{43}$/'],
        ]);
        abort_unless($this->isAllowedReturnUrl($data['redirect']), 422, __('That return address is not allowed.'));
        abort_unless($verifier->enabled() && config('services.google.client_secret'), 503, __('Google sign-in is not available right now.'));

        $nonce = Str::random(32);
        $state = Crypt::encryptString(json_encode(['r' => $data['redirect'], 'c' => $data['challenge'], 'n' => $nonce, 'e' => time() + 600]));

        return redirect()->away(self::AUTH_URL.'?'.http_build_query([
            'client_id' => $verifier->clientIds()[0],
            'redirect_uri' => $this->callbackUrl(),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'prompt' => 'select_account',
        ]));
    }

    public function callback(Request $request, GoogleIdTokenVerifier $verifier, GoogleAccounts $accounts): RedirectResponse
    {
        $state = $this->readState((string) $request->query('state'));
        // Without a valid state we do not know where to send the browser, so show a plain error.
        abort_unless($state, 400, __('This sign-in link is invalid or has expired. Please go back to the app and try again.'));

        $back = fn (array $params) => redirect()->away($this->withQuery($state['r'], $params));

        if ($request->filled('error') || ! $request->filled('code')) {
            return $back(['error' => 'cancelled']);
        }

        try {
            $idToken = $this->exchangeCodeForIdToken((string) $request->query('code'), $verifier);
            $claims = $idToken ? $verifier->verify($idToken) : null;
            if (! $claims || ! hash_equals($state['n'], (string) ($claims['nonce'] ?? ''))) {
                return $back(['error' => 'failed']);
            }
            [$user, $created] = $accounts->resolve($claims);
            $accounts->assertMayUseApp($user);
        } catch (HttpException $e) {
            return $back(['error' => 'denied', 'message' => $e->getMessage()]);
        }

        $code = Str::random(40);
        Cache::put(self::CODE_PREFIX.hash('sha256', $code), ['user_id' => $user->id, 'challenge' => $state['c'], 'created' => $created], now()->addMinutes(2));

        return $back(['code' => $code]);
    }

    /** Trades Google's authorization code for the ID token (needs the client secret, so it only happens here). */
    protected function exchangeCodeForIdToken(string $code, GoogleIdTokenVerifier $verifier): ?string
    {
        try {
            $response = Http::asForm()->timeout(10)->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => $verifier->clientIds()[0],
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => $this->callbackUrl(),
                'grant_type' => 'authorization_code',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Google code exchange failed: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Google code exchange was rejected (HTTP '.$response->status().'): '.$response->json('error'));

            return null;
        }

        return $response->json('id_token');
    }

    /** The address registered in Google Cloud as an authorised redirect URI. */
    protected function callbackUrl(): string
    {
        return config('services.google.redirect') ?: route('auth.google.app.callback');
    }

    /** @return array{r: string, c: string, n: string, e: int}|null */
    protected function readState(string $state): ?array
    {
        try {
            $payload = json_decode(Crypt::decryptString($state), true);
        } catch (DecryptException) {
            return null;
        }

        return is_array($payload) && ($payload['e'] ?? 0) > time() && $this->isAllowedReturnUrl((string) ($payload['r'] ?? '')) ? $payload : null;
    }

    /**
     * Where the browser may be sent back to. Only the app's own link scheme is accepted, never a web
     * address, so this cannot be used as an open redirect. Expo Go's exp:// links are for development only.
     */
    protected function isAllowedReturnUrl(string $url): bool
    {
        if (preg_match('/[\s<>"\\\\]/', $url) || str_contains($url, '#')) {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'faithfellows' || (in_array($scheme, ['exp', 'exps'], true) && ! app()->isProduction());
    }

    /** @param array<string, string> $params */
    protected function withQuery(string $url, array $params): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($params);
    }
}
