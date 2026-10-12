<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/** A valid sign-up body; override or drop keys per test. */
function signUpData(array $overrides = []): array
{
    return [
        'name' => 'Grace Akello',
        'email' => 'grace.signup@example.com',
        'phone' => '0772 123 456',
        'password' => 'a-good-password-1',
        'password_confirmation' => 'a-good-password-1',
        'terms' => true,
        'device_name' => 'iPhone',
        ...$overrides,
    ];
}

test('signing up from the app creates a member, asks them to verify their email and signs them in', function () {
    Notification::fake();

    $response = $this->postJson(route('api.v1.auth.register'), signUpData(['name' => '  Grace Akello  ', 'email' => '  Grace.SignUp@Example.com ']))
        ->assertCreated()
        ->assertJsonPath('is_new_user', true)
        ->assertJsonPath('user.name', 'Grace Akello')
        ->assertJsonPath('user.email', 'grace.signup@example.com')
        ->assertJsonStructure(['token', 'token_type', 'expires_at', 'user' => ['id', 'churches']]);

    $user = User::where('email', 'grace.signup@example.com')->sole();
    expect($user->hasRole('member'))->toBeTrue()
        ->and($user->terms_accepted_at)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->phone)->toBe('0772 123 456')
        ->and(Hash::check('a-good-password-1', $user->password))->toBeTrue();
    Notification::assertSentTo($user, VerifyEmail::class);

    app('auth')->forgetGuards();
    $this->withToken($response->json('token'))->getJson(route('api.v1.me'))->assertOk()->assertJsonPath('data.email', 'grace.signup@example.com');
});

test('a phone number is optional', function () {
    $this->postJson(route('api.v1.auth.register'), signUpData(['phone' => null]))->assertCreated();
    $this->postJson(route('api.v1.auth.register'), array_diff_key(signUpData(['email' => 'second@example.com']), ['phone' => 1]))->assertCreated();
});

test('sign-up refuses incomplete or unsafe details and creates nobody', function (array $overrides, string $field) {
    member(['email' => 'taken@example.com']);
    member(['email' => 'closed@example.com'])->delete();
    $before = User::withTrashed()->count();

    $this->postJson(route('api.v1.auth.register'), signUpData($overrides))->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(User::withTrashed()->count())->toBe($before);
})->with([
    'no name' => [['name' => ''], 'name'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'email already registered' => [['email' => 'taken@example.com'], 'email'],
    'same email, different capitals' => [['email' => 'Taken@Example.com'], 'email'],
    'email of a closed account' => [['email' => 'closed@example.com'], 'email'],
    'password too short' => [['password' => 'short1', 'password_confirmation' => 'short1'], 'password'],
    'passwords differ' => [['password_confirmation' => 'something-else-1'], 'password'],
    'terms not accepted' => [['terms' => false], 'terms'],
    'terms missing' => [['terms' => null], 'terms'],
    'phone with letters' => [['phone' => 'call me'], 'phone'],
    'no device name' => [['device_name' => ''], 'device_name'],
]);

test('sign-up from the app is rate limited per address', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson(route('api.v1.auth.register'), signUpData(['name' => '']))->assertUnprocessable();
    }

    $this->postJson(route('api.v1.auth.register'), signUpData())->assertStatus(429);
    expect(User::count())->toBe(0);
});

test('website sign-up now accepts capital letters in the email and still creates a member', function () {
    $this->post('/register', [
        'name' => 'Web Member', 'email' => 'Web.Member@Example.com', 'password' => 'a-good-password-1', 'password_confirmation' => 'a-good-password-1', 'terms' => '1',
    ])->assertRedirect();

    expect(User::where('email', 'web.member@example.com')->sole()->hasRole('member'))->toBeTrue();
});
