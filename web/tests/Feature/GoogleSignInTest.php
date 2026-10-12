<?php

use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

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
