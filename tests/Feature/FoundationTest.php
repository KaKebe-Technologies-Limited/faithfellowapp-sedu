<?php

use App\Mail\ContactMessageReceived;
use App\Models\BlogPost;
use App\Models\ContactMessage;
use App\Models\User;
use App\Notifications\Auth\MagicLoginLink;
use App\Notifications\Auth\TwoFactorCode;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

test('public marketing pages render', function (string $path) {
    $this->get($path)->assertOk()->assertSee('FaithFellows');
})->with(['/', '/about', '/services', '/pricing', '/contact', '/blog', '/privacy', '/terms', '/offline']);

test('landing page uses the brand copy', function () {
    $this->get('/')
        ->assertSee('Empowering Your Ministry in the')
        ->assertSee('Traditional methods of reaching and engaging congregations', false);
});

test('blog shows only published posts', function () {
    $published = BlogPost::create(['title' => 'Live post', 'slug' => 'live-post', 'body' => '<p>Hi</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
    BlogPost::create(['title' => 'Draft post', 'slug' => 'draft-post', 'body' => '<p>Hi</p>', 'status' => 'draft']);

    $this->get('/blog')->assertSee('Live post')->assertDontSee('Draft post');
    $this->get('/blog/'.$published->slug)->assertOk();
    $this->get('/blog/draft-post')->assertNotFound();
});

test('blog body is sanitised against XSS', function () {
    BlogPost::create(['title' => 'X', 'slug' => 'x', 'status' => 'published', 'published_at' => now()->subDay(),
        'body' => '<p onclick="alert(1)">Safe</p><script>alert(2)</script><a href="javascript:alert(3)">x</a>']);

    $this->get('/blog/x')
        ->assertDontSee('<script>alert(2)</script>', false)
        ->assertDontSee('onclick=', false)
        ->assertDontSee('javascript:alert', false);
});

test('contact form stores the message and emails the team', function () {
    Mail::fake();

    $this->post('/contact', [
        'name' => 'Pastor John', 'email' => 'john@example.com', 'phone' => '0772000000',
        'subject' => 'Onboarding', 'message' => 'We would like to onboard our church please.',
    ])->assertRedirect(route('contact'))->assertSessionHas('success');

    expect(ContactMessage::where('email', 'john@example.com')->exists())->toBeTrue();
    Mail::assertQueued(ContactMessageReceived::class, fn ($m) => $m->hasTo(config('faithfellows.company.email')));
});

test('contact form honeypot blocks bots', function () {
    $this->post('/contact', [
        'name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Buy cheap things now!!', 'website' => 'http://spam',
    ])->assertSessionHasErrors('website');

    expect(ContactMessage::count())->toBe(0);
});

test('super admin area is restricted to super admins', function () {
    $this->actingAs(member())->get('/super-admin/dashboard')->assertForbidden();
    $this->actingAs(superAdmin())->get('/super-admin/dashboard')->assertOk();
});

test('super admins are redirected to the super admin dashboard after login', function () {
    $admin = superAdmin();

    $this->post('/login', ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect(route('super-admin.dashboard'));
});

test('suspended users cannot log in', function () {
    $user = member(['status' => 'suspended']);

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);
    $this->assertGuest();
});

test('two factor login requires the emailed code', function () {
    Notification::fake();
    $user = member(['two_factor_enabled' => true]);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('two-factor.challenge'));
    $this->assertGuest();

    $code = null;
    Notification::assertSentTo($user, TwoFactorCode::class, function ($n) use (&$code) {
        $code = $n->code;

        return true;
    });

    $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');
    $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('magic link signs the user in', function () {
    Notification::fake();
    $user = member();

    $this->post('/login/magic', ['email' => $user->email])->assertSessionHas('status');

    $url = null;
    Notification::assertSentTo($user, MagicLoginLink::class, function ($n) use (&$url) {
        $url = $n->url;

        return true;
    });

    $this->get($url)->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('users can update preferences including quiet hours', function () {
    $user = member();

    $this->actingAs($user)->patch('/profile/preferences', [
        'quiet_hours_start' => '22:00', 'quiet_hours_end' => '06:00',
        'digest_mode' => '1', 'low_bandwidth' => '0', 'directory_opt_in' => '1', 'email_consent' => '0', 'two_factor_enabled' => '0',
    ])->assertRedirect();

    $user->refresh();
    expect($user->digest_mode)->toBeTrue()
        ->and($user->inQuietHours(now('Africa/Kampala')->setTime(23, 30)))->toBeTrue()
        ->and($user->inQuietHours(now('Africa/Kampala')->setTime(12, 0)))->toBeFalse();
});

test('users can export their data', function () {
    $user = member();

    $response = $this->actingAs($user)->get('/profile/export');
    $response->assertOk()->assertHeader('content-type', 'application/json');
    expect($response->streamedContent())->toContain($user->email);
});

test('deleting an account anonymises personal data', function () {
    $user = member(['name' => 'Real Name', 'phone' => '0772999999']);

    $this->actingAs($user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');

    $deleted = User::withTrashed()->find($user->id);
    expect($deleted->name)->toBe('Deleted user')
        ->and($deleted->phone)->toBeNull()
        ->and($deleted->trashed())->toBeTrue();
});
