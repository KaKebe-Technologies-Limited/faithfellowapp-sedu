<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $this->get('/register')->assertOk();
});

test('new users can register and become members', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => '0772123456',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
        'email_consent' => '1',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'test@example.com')->first();
    expect($user->hasRole('member'))->toBeTrue()
        ->and($user->email_consent)->toBeTrue()
        ->and($user->terms_accepted_at)->not->toBeNull();
});

test('registration requires accepting the terms', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('terms');

    $this->assertGuest();
});
