<?php

use App\Models\PlatformSetting;

/*
| The flash of rolling photos when the app opens: the platform admin sets whether it shows, how long it stays, how fast the photos roll and the words
| (Super admin → Settings). The app reads it before anyone has signed in and keeps the answer for the next time it opens.
*/

beforeEach(fn () => Cache::forget('platform_settings'));

test('the app is told how to open, with no sign-in: six seconds and the standard words until the admin says otherwise', function () {
    $this->getJson(route('api.v1.app.opening'))->assertOk()->assertJson(['data' => [
        'enabled' => true, 'seconds' => 6, 'speed' => 5, 'title' => 'Stay Connected in Faith',
    ]])->assertJsonPath('data.text', fn ($t) => str_contains($t, 'church family'));
});

test('the super admin sets how long the photos stay, how fast they roll and the words, and the app hears it', function () {
    $this->actingAs(superAdmin())->put(route('super-admin.settings.update'), [
        'opening_flash_enabled' => 1, 'opening_flash_seconds' => 9, 'opening_flash_speed' => 3, 'opening_flash_title' => 'Welcome home', 'opening_flash_text' => 'God is with us.',
    ])->assertSessionHasNoErrors();

    $this->getJson(route('api.v1.app.opening'))->assertOk()->assertJson(['data' => ['enabled' => true, 'seconds' => 9, 'speed' => 3, 'title' => 'Welcome home', 'text' => 'God is with us.']]);
});

test('the flash can be switched off, and empty words go back to the standard ones', function () {
    $this->actingAs(superAdmin())->put(route('super-admin.settings.update'), ['opening_flash_seconds' => 8, 'opening_flash_title' => 'Hello'])->assertSessionHasNoErrors(); // the switch is not sent: off
    $this->getJson(route('api.v1.app.opening'))->assertJsonPath('data.enabled', false)->assertJsonPath('data.seconds', 8)->assertJsonPath('data.title', 'Hello');

    $this->actingAs(superAdmin())->put(route('super-admin.settings.update'), ['opening_flash_enabled' => 1, 'opening_flash_seconds' => '', 'opening_flash_title' => ''])->assertSessionHasNoErrors();
    $this->getJson(route('api.v1.app.opening'))->assertJsonPath('data.enabled', true)->assertJsonPath('data.seconds', 6)->assertJsonPath('data.title', 'Stay Connected in Faith');
});

test('the times are kept within what the screen can use', function () {
    foreach ([['opening_flash_seconds' => 1], ['opening_flash_seconds' => 21], ['opening_flash_speed' => 0], ['opening_flash_speed' => 11]] as $bad) {
        $this->actingAs(superAdmin())->put(route('super-admin.settings.update'), $bad)->assertSessionHasErrors(array_key_first($bad));
    }

    // A value saved some other way is still held to the limits when the app asks.
    PlatformSetting::put('opening_flash_seconds', '500');
    PlatformSetting::put('opening_flash_speed', '0');
    $this->getJson(route('api.v1.app.opening'))->assertJsonPath('data.seconds', 20)->assertJsonPath('data.speed', 1);
});
