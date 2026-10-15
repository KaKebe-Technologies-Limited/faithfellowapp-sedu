<?php

use App\Models\PlatformSetting;

test('the home page is short and shows the main sections', function () {
    $this->get('/')->assertOk()
        ->assertSee('Pray. Give.', false)
        ->assertSee('Everything in one app')
        ->assertSee('Start in 3 steps')
        ->assertSee('Register your church')
        ->assertSee('Empowering Your Ministry in the Digital Age');
});

test('the menu points to the home page sections and the main pages', function () {
    $this->get(route('pricing'))->assertOk()
        ->assertSee(route('home').'#features', false)
        ->assertSee(route('home').'#how', false)
        ->assertSee(route('pricing'), false)
        ->assertSee(route('blog.index'), false);
});

test('the download buttons use the link set by the super admin', function () {
    PlatformSetting::put('android_app_url', 'https://downloads.example.com/faithfellows.apk');

    $this->get('/')->assertOk()->assertSee('https://downloads.example.com/faithfellows.apk', false);
});

test('without a download link the buttons lead to the sign-up page', function () {
    config(['faithfellows.app.android_url' => null]);

    expect(ff_app_download_url())->toBeNull();
    $this->get('/')->assertOk()->assertSee(route('register'), false)->assertDontSee('expo.dev/artifacts', false);
});

test('the super admin can change the download link', function () {
    $this->actingAs(superAdmin())
        ->put(route('super-admin.settings.update'), ['android_app_url' => 'https://play.google.com/store/apps/details?id=tech.kakebe.faithfellows'])
        ->assertSessionHasNoErrors();

    expect(ff_app_download_url())->toBe('https://play.google.com/store/apps/details?id=tech.kakebe.faithfellows');

    $this->put(route('super-admin.settings.update'), ['android_app_url' => 'not a link'])->assertSessionHasErrors('android_app_url');
});
