<?php

use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

const GOOGLE_TEST_CLIENT = 'test-web-client.apps.googleusercontent.com';

/*
|--------------------------------------------------------------------------
| Helpers: sign real RS256 tokens with a throwaway key and serve its public
| half where Google's certificates would be.
|--------------------------------------------------------------------------
*/

function googleTestKey(): OpenSSLAsymmetricKey
{
    static $key = null;
    if ($key) {
        return $key;
    }

    $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
    $key = @openssl_pkey_new($options);
    // XAMPP on Windows needs OpenSSL's config file named explicitly.
    foreach ([getenv('OPENSSL_CONF'), dirname(PHP_BINARY).'/extras/ssl/openssl.cnf', 'C:/xampp/apache/conf/openssl.cnf'] as $config) {
        if (! $key && $config && is_file($config)) {
            $key = @openssl_pkey_new([...$options, 'config' => $config]);
        }
    }

    return $key ?: test()->markTestSkipped('OpenSSL cannot create an RSA key in this environment.');
}

function base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

/** @param array<string, mixed> $claims */
function googleToken(array $claims = [], string $kid = 'test-key', string $alg = 'RS256'): string
{
    $claims = [
        'iss' => 'https://accounts.google.com',
        'aud' => GOOGLE_TEST_CLIENT,
        'sub' => '110011001100',
        'email' => 'grace.new@gmail.com',
        'email_verified' => true,
        'name' => 'Grace New',
        'iat' => time() - 10,
        'exp' => time() + 3600,
        ...$claims,
    ];
    $signing = base64url(json_encode(['alg' => $alg, 'kid' => $kid, 'typ' => 'JWT'])).'.'.base64url(json_encode($claims));
    openssl_sign($signing, $signature, googleTestKey(), OPENSSL_ALGO_SHA256);

    return $signing.'.'.base64url($signature);
}

function signInWithGoogle(string $credential, array $extra = [])
{
    return test()->postJson(route('api.v1.auth.google'), ['credential' => $credential, 'device_name' => 'iPhone', ...$extra]);
}

beforeEach(function () {
    config(['services.google.client_ids' => [GOOGLE_TEST_CLIENT]]);
    Cache::flush();
    Http::fake(['www.googleapis.com/oauth2/v1/certs' => Http::response(
        ['test-key' => openssl_pkey_get_details(googleTestKey())['key']], 200, ['Cache-Control' => 'public, max-age=3600'],
    )]);
});

/*
|--------------------------------------------------------------------------
| Signing in and signing up
|--------------------------------------------------------------------------
*/

test('a first Google sign-in creates a verified member and returns a working token', function () {
    $response = signInWithGoogle(googleToken())->assertCreated()
        ->assertJsonPath('is_new_user', true)
        ->assertJsonPath('user.email', 'grace.new@gmail.com')
        ->assertJsonPath('user.name', 'Grace New')
        ->assertJsonStructure(['token', 'token_type', 'expires_at', 'user' => ['id', 'churches']]);

    $user = User::where('email', 'grace.new@gmail.com')->sole();
    expect($user->google_id)->toBe('110011001100')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->terms_accepted_at)->not->toBeNull()
        ->and($user->hasRole('member'))->toBeTrue()
        ->and($user->password)->not->toBeEmpty();

    app('auth')->forgetGuards();
    $this->withToken($response->json('token'))->getJson(route('api.v1.me'))->assertOk()->assertJsonPath('data.email', 'grace.new@gmail.com');
});

test('an existing member is signed in by email, linked to Google, and not duplicated', function () {
    $member = member(['email' => 'grace.new@gmail.com']);

    signInWithGoogle(googleToken())->assertCreated()->assertJsonPath('is_new_user', false)->assertJsonPath('user.id', $member->id);

    expect(User::where('email', 'grace.new@gmail.com')->count())->toBe(1)
        ->and($member->fresh()->google_id)->toBe('110011001100');

    // Next time they are found by their Google id, even if the Google address changed.
    signInWithGoogle(googleToken(['email' => 'new.address@gmail.com']))->assertCreated()->assertJsonPath('user.id', $member->id);
    expect(User::count())->toBe(1);
});

test('linking an account whose email was never verified cuts off any password someone else set', function () {
    $victim = member(['email' => 'grace.new@gmail.com', 'email_verified_at' => null, 'password' => Hash::make('attacker-knows-this')]);
    $victim->createToken('attacker phone');

    signInWithGoogle(googleToken())->assertCreated();

    $victim->refresh();
    expect(Hash::check('attacker-knows-this', $victim->password))->toBeFalse()
        ->and($victim->email_verified_at)->not->toBeNull()
        ->and($victim->tokens()->count())->toBe(1); // only the token just issued
});

/*
|--------------------------------------------------------------------------
| Tokens that must be refused
|--------------------------------------------------------------------------
*/

test('invalid Google tokens are refused and create nobody', function (string $case) {
    $credential = match ($case) {
        'wrong audience' => googleToken(['aud' => 'someone-elses-client.apps.googleusercontent.com']),
        'expired' => googleToken(['exp' => time() - 3600]),
        'issued in the future' => googleToken(['iat' => time() + 3600]),
        'wrong issuer' => googleToken(['iss' => 'https://evil.example.com']),
        'email not verified' => googleToken(['email_verified' => false]),
        'no email' => googleToken(['email' => '']),
        'unknown key id' => googleToken(kid: 'rotated-away'),
        'not RS256' => googleToken(alg: 'HS256'),
        'tampered payload' => (function () {
            [$h, , $s] = explode('.', googleToken());

            return $h.'.'.base64url(json_encode(['iss' => 'https://accounts.google.com', 'aud' => GOOGLE_TEST_CLIENT, 'sub' => '1', 'email' => 'admin@faithfellows.app', 'email_verified' => true, 'iat' => time(), 'exp' => time() + 3600])).'.'.$s;
        })(),
        'not a token' => 'not-a-jwt',
    };

    signInWithGoogle($credential)->assertUnauthorized();

    expect(User::count())->toBe(0);
})->with(['wrong audience', 'expired', 'issued in the future', 'wrong issuer', 'email not verified', 'no email', 'unknown key id', 'not RS256', 'tampered payload', 'not a token']);

test('Google sign-in fails safely when Google cannot be reached', function () {
    Cache::flush();
    Http::swap(new Factory); // drop the happy-path stub from beforeEach
    Http::fake(['www.googleapis.com/*' => Http::response('down', 503)]);

    signInWithGoogle(googleToken())->assertUnauthorized();
    expect(User::count())->toBe(0);
});

test('Google sign-in is switched off until a client id is configured', function () {
    config(['services.google.client_ids' => []]);

    signInWithGoogle(googleToken())->assertStatus(503);
});

test('the request must include the credential and a device name', function () {
    $this->postJson(route('api.v1.auth.google'), ['device_name' => 'iPhone'])->assertUnprocessable()->assertJsonValidationErrors('credential');
    $this->postJson(route('api.v1.auth.google'), ['credential' => googleToken()])->assertUnprocessable()->assertJsonValidationErrors('device_name');
});

/*
|--------------------------------------------------------------------------
| Accounts that may not sign in this way
|--------------------------------------------------------------------------
*/

test('suspended, closed and platform-staff accounts are refused', function () {
    member(['email' => 'suspended@gmail.com', 'status' => 'suspended']);
    signInWithGoogle(googleToken(['email' => 'suspended@gmail.com', 'sub' => '1']))->assertForbidden();

    member(['email' => 'closed@gmail.com'])->delete();
    signInWithGoogle(googleToken(['email' => 'closed@gmail.com', 'sub' => '2']))->assertForbidden();
    expect(User::withTrashed()->where('email', 'closed@gmail.com')->count())->toBe(1);

    superAdmin()->update(['email' => 'boss@gmail.com']);
    signInWithGoogle(googleToken(['email' => 'boss@gmail.com', 'sub' => '3']))->assertForbidden();
});

test('an account linked to one Google account cannot be taken over by another with the same email', function () {
    $member = member(['email' => 'grace.new@gmail.com']);
    $member->forceFill(['google_id' => 'the-real-owner'])->save();

    signInWithGoogle(googleToken(['sub' => 'someone-else']))->assertForbidden();

    expect($member->fresh()->google_id)->toBe('the-real-owner');
});

/*
|--------------------------------------------------------------------------
| Hosted-page flow for the mobile app (system browser → Google → back to the app)
|--------------------------------------------------------------------------
*/

/** Runs /auth/google/app/start like the app does and returns what the browser would be sent to Google with. */
function startGoogleApp(string $redirect = 'faithfellows://auth', ?string $verifier = null): array
{
    $verifier ??= Str::random(64);
    $challenge = base64url(hash('sha256', $verifier, true));
    $response = test()->get(route('auth.google.app.start', ['redirect' => $redirect, 'challenge' => $challenge]));
    $response->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');
    parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

    return [$query, $verifier, json_decode(Crypt::decryptString($query['state']), true)];
}

/** Completes the flow as Google would: the callback with a code, and Google's token endpoint answering with an ID token. */
function finishGoogleApp(array $query, array $claims = [], int $tokenStatus = 200)
{
    Http::fake(['oauth2.googleapis.com/token' => $tokenStatus === 200
        ? Http::response(['id_token' => googleToken(['nonce' => $query['nonce'], ...$claims])])
        : Http::response(['error' => 'invalid_grant'], $tokenStatus)]);

    return test()->get(route('auth.google.app.callback', ['code' => 'google-auth-code', 'state' => $query['state']]));
}

/** @return array<string, string> query parameters of the app link the browser was sent back to */
function appLinkQuery($response, string $prefix = 'faithfellows://auth'): array
{
    $location = $response->headers->get('Location');
    expect($location)->toStartWith($prefix);
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    return $query;
}

beforeEach(function () {
    config(['services.google.client_secret' => 'test-secret', 'services.google.redirect' => null]);
});

test('the app starts Google sign-in with the right request and an encrypted state', function () {
    [$query, , $state] = startGoogleApp('faithfellows://auth');

    expect($query)->toMatchArray([
        'client_id' => GOOGLE_TEST_CLIENT,
        'redirect_uri' => route('auth.google.app.callback'),
        'response_type' => 'code',
        'scope' => 'openid email profile',
    ])->and($state['r'])->toBe('faithfellows://auth')
        ->and($state['n'])->toBe($query['nonce'])
        ->and($state['e'])->toBeGreaterThan(time());
});

test('only the apps own link is accepted as the return address', function (string $redirect) {
    $challenge = base64url(hash('sha256', 'verifier', true));

    $this->get(route('auth.google.app.start', ['redirect' => $redirect, 'challenge' => $challenge]))->assertStatus(422);
})->with(['https://evil.example.com/steal', 'http://localhost/x', 'javascript:alert(1)', '//evil.example.com', 'faithfellows://auth#fragment', 'faithfellows://a b']);

test('Expo Go return links work in development only', function () {
    startGoogleApp('exp://192.168.0.103:8081/--/auth');

    app()->detectEnvironment(fn () => 'production');
    $this->get(route('auth.google.app.start', ['redirect' => 'exp://192.168.0.103:8081/--/auth', 'challenge' => base64url(hash('sha256', 'v', true))]))->assertStatus(422);
});

test('starting needs a well-formed challenge and a configured client secret', function () {
    $this->get(route('auth.google.app.start', ['redirect' => 'faithfellows://auth', 'challenge' => 'too-short']))->assertSessionHasErrors('challenge');

    config(['services.google.client_secret' => null]);
    $this->get(route('auth.google.app.start', ['redirect' => 'faithfellows://auth', 'challenge' => base64url(hash('sha256', 'v', true))]))->assertStatus(503);
});

test('a completed Google sign-in returns to the app with a one-time code that signs the member in', function () {
    [$query, $verifier] = startGoogleApp();

    $link = appLinkQuery(finishGoogleApp($query));
    expect($link)->toHaveKey('code')->and(strlen($link['code']))->toBe(40);
    Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/token'
        && $request['code'] === 'google-auth-code' && $request['client_secret'] === 'test-secret' && $request['grant_type'] === 'authorization_code');

    $token = $this->postJson(route('api.v1.auth.google.exchange'), ['code' => $link['code'], 'verifier' => $verifier, 'device_name' => 'iPhone'])
        ->assertCreated()->assertJsonPath('is_new_user', true)->assertJsonPath('user.email', 'grace.new@gmail.com')->json('token');

    app('auth')->forgetGuards();
    $this->withToken($token)->getJson(route('api.v1.me'))->assertOk();
    expect(User::where('email', 'grace.new@gmail.com')->sole()->hasRole('member'))->toBeTrue();

    // The code is single-use.
    $this->postJson(route('api.v1.auth.google.exchange'), ['code' => $link['code'], 'verifier' => $verifier, 'device_name' => 'iPhone'])->assertUnauthorized();
});

test('a one-time code is useless without the verifier of the app that started the sign-in', function () {
    [$query] = startGoogleApp();
    $link = appLinkQuery(finishGoogleApp($query));

    // Someone who intercepts the app link cannot redeem it, and the code is burned after one wrong try.
    $this->postJson(route('api.v1.auth.google.exchange'), ['code' => $link['code'], 'verifier' => Str::random(64), 'device_name' => 'attacker'])->assertUnauthorized();
    $this->postJson(route('api.v1.auth.google.exchange'), ['code' => $link['code'], 'verifier' => Str::random(64), 'device_name' => 'attacker'])->assertUnauthorized();
});

test('one-time codes expire after two minutes', function () {
    [$query, $verifier] = startGoogleApp();
    $link = appLinkQuery(finishGoogleApp($query));

    $this->travel(3)->minutes();

    $this->postJson(route('api.v1.auth.google.exchange'), ['code' => $link['code'], 'verifier' => $verifier, 'device_name' => 'iPhone'])->assertUnauthorized();
});

test('failures send the browser back to the app with an error, creating nobody', function () {
    // A token meant for another request (wrong nonce) is not accepted.
    [$query] = startGoogleApp();
    expect(appLinkQuery(finishGoogleApp($query, ['nonce' => 'replayed-from-elsewhere'])))->toMatchArray(['error' => 'failed']);

    // Google refuses the authorisation code.
    [$query] = startGoogleApp();
    expect(appLinkQuery(finishGoogleApp($query, tokenStatus: 400)))->toMatchArray(['error' => 'failed']);

    // The person cancelled on Google's screen.
    [$query] = startGoogleApp();
    $cancelled = $this->get(route('auth.google.app.callback', ['error' => 'access_denied', 'state' => $query['state']]));
    expect(appLinkQuery($cancelled))->toMatchArray(['error' => 'cancelled']);

    expect(User::count())->toBe(0);
});

test('a suspended member is sent back with a clear message and no code', function () {
    member(['email' => 'grace.new@gmail.com', 'status' => 'suspended']);
    [$query] = startGoogleApp();

    $link = appLinkQuery(finishGoogleApp($query));

    expect($link)->toMatchArray(['error' => 'denied', 'message' => 'This account is suspended.'])->not->toHaveKey('code');
});

test('the callback refuses a missing, forged or expired state', function () {
    $this->get(route('auth.google.app.callback', ['code' => 'x']))->assertStatus(400);
    $this->get(route('auth.google.app.callback', ['code' => 'x', 'state' => 'forged']))->assertStatus(400);

    $expired = Crypt::encryptString(json_encode(['r' => 'faithfellows://auth', 'c' => str_repeat('a', 43), 'n' => 'n', 'e' => time() - 1]));
    $this->get(route('auth.google.app.callback', ['code' => 'x', 'state' => $expired]))->assertStatus(400);

    // A state that points somewhere other than the app is refused, even though it is genuinely encrypted.
    $evil = Crypt::encryptString(json_encode(['r' => 'https://evil.example.com', 'c' => str_repeat('a', 43), 'n' => 'n', 'e' => time() + 600]));
    $this->get(route('auth.google.app.callback', ['code' => 'x', 'state' => $evil]))->assertStatus(400);
});
