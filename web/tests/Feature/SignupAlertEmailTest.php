<?php

use App\Models\Church;
use App\Notifications\Admin\NewSignup;
use App\Services\SignupAlerts;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/*
| The owner is emailed about every new account, with the details of the person, and the email goes out at once (a shared server may have no
| queue worker running).
*/

test('the email carries the details of the person and a link to them', function () {
    $person = member(['name' => 'Grace Akello', 'email' => 'grace@example.com', 'phone' => '0772123456', 'country' => 'UG', 'city' => 'Lira', 'language' => 'en']);
    $mail = (new NewSignup($person, 1250))->toMail(new stdClass);
    $details = $mail->viewData['details'];

    expect($mail->subject)->toBe('New sign-up: Grace Akello')
        ->and($details)->toMatchArray(['Name' => 'Grace Akello', 'Email' => 'grace@example.com', 'Phone' => '0772123456', 'Town' => 'Lira', 'Member number' => '#1,250'])
        ->and($details['Country'])->toBe('Uganda')
        ->and($details['Signed up with'])->toBe('Email and password')
        ->and($mail->viewData['button']['url'])->toBe(route('super-admin.users.show', $person));
});

test('the email says which churches the person follows when they already follow one', function () {
    [$church, , $follower] = (function () {
        $church = Church::factory()->create(['name' => 'Grace Community Church Lira']);
        $follower = member();
        $church->subscriptions()->create(['user_id' => $follower->id, 'status' => 'active', 'subscribed_at' => now()]);

        return [$church, null, $follower];
    })();

    expect((new NewSignup($follower, 5))->toMail(new stdClass)->viewData['details']['Follows'])->toBe($church->name);
});

test('the alert is sent at once and does not wait for a queue worker', function () {
    Queue::fake();
    superAdmin();
    $person = member(['name' => 'Grace Akello']);
    app(SignupAlerts::class)->save(true, 'ot@example.com');

    expect(app(SignupAlerts::class)->notify($person))->toBe(1);
    Queue::assertNothingPushed();
});

test('signing up in the app emails the owner without a queue worker', function () {
    Notification::fake();
    config(['faithfellows.signup_alerts_in_console' => true]);
    app(SignupAlerts::class)->save(true, 'owner@gmail.example');

    $this->postJson(route('api.v1.auth.register'), [
        'name' => 'Grace Akello', 'email' => 'grace.new@example.com', 'phone' => '0772 123 456', 'password' => 'a-good-password-1',
        'password_confirmation' => 'a-good-password-1', 'terms' => true, 'device_name' => 'Pixel',
    ])->assertCreated();

    Notification::assertSentOnDemand(NewSignup::class, fn (NewSignup $n, $channels, $notifiable) => $notifiable->routes['mail'] === 'owner@gmail.example' && $n->user->email === 'grace.new@example.com');
});
