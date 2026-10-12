<?php

use App\Models\User;

$profile = fn (array $overrides = []) => array_merge([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'country' => 'UG',
    'language' => 'en',
    'timezone' => 'Africa/Kampala',
], $overrides);

test('profile page is displayed', function () {
    $this->actingAs(member())->get('/profile')->assertOk();
});

test('profile information can be updated', function () use ($profile) {
    $user = member();

    $this->actingAs($user)->patch('/profile', $profile(['phone' => '0772123456', 'language' => 'lg']))
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com')
        ->and($user->language)->toBe('lg')
        ->and($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () use ($profile) {
    $user = member();

    $this->actingAs($user)->patch('/profile', $profile(['email' => $user->email]))
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = member();

    $this->actingAs($user)->delete('/profile', ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    expect(User::find($user->id))->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = member();

    $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => 'wrong-password'])
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');

    expect($user->fresh())->not->toBeNull();
});
