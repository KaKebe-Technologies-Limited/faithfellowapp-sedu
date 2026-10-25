<?php

use App\Models\Inspiration;
use Laravel\Sanctum\Sanctum;

/*
| Today's word on its own: the Bible tab shows it without loading the whole home screen.
*/

test('today\'s word is answered on its own, and is empty when there is none', function () {
    Inspiration::query()->delete(); // the app ships with a library of messages; start from none
    Sanctum::actingAs(member(), ['member']);
    $this->getJson(route('api.v1.inspiration'))->assertOk()->assertJson(['data' => null]);

    foreach (array_keys(Inspiration::SLOTS) as $slot) {
        Inspiration::create(['slot' => $slot, 'body' => 'Be strong and courageous.', 'reference' => 'Joshua 1:9', 'is_active' => true]);
    }
    $data = $this->getJson(route('api.v1.inspiration'))->assertOk()->json('data');

    expect($data)->toHaveKeys(['slot', 'label', 'body', 'reference'])->and($data['body'])->toBe('Be strong and courageous.')->and($data['reference'])->toBe('Joshua 1:9');
});

test('today\'s word needs a sign-in', function () {
    $this->getJson(route('api.v1.inspiration'))->assertUnauthorized();
});
