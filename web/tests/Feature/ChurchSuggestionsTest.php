<?php

use App\Models\Church;
use Laravel\Sanctum\Sanctum;

/*
| A new member who follows no church is shown churches to follow: the nearest to where the phone is now (only used for that answer, never saved),
| then churches in their city and country, then the best known. Churches already followed are left out.
*/

function suggestedBy(array $attributes = []): array
{
    $user = member(['city' => 'Lira', 'country' => 'UG', ...$attributes]);
    Sanctum::actingAs($user, ['member']);

    return [$user];
}

test('the churches nearest to the phone come first, with how far they are, and the place is not saved', function () {
    [$user] = suggestedBy();
    $near = Church::factory()->create(['name' => 'Near Church', 'city' => 'Lira', 'lat' => 2.2499, 'lng' => 32.8998]);
    $farther = Church::factory()->create(['name' => 'Farther Church', 'city' => 'Gulu', 'lat' => 2.7746, 'lng' => 32.2990]);
    Church::factory()->create(['name' => 'Kampala Church', 'city' => 'Kampala', 'lat' => 0.3476, 'lng' => 32.5825]);

    $answer = $this->postJson(route('api.v1.churches.suggest'), ['latitude' => 2.2501, 'longitude' => 32.9002])->assertOk()->json();
    $names = collect($answer['data'])->pluck('name');

    expect($answer['located'])->toBeTrue()
        ->and($names->take(2)->all())->toBe(['Near Church', 'Farther Church'])
        ->and($answer['data'][0]['distance_label'])->toBe('Less than 1 km away')
        ->and($answer['data'][0]['why'])->toBe('Less than 1 km away')
        ->and($answer['data'][1]['distance_label'])->toStartWith('About ')
        ->and($names->all())->toContain('Kampala Church'); // beyond 100 km it is still offered, as "in your country"
    expect($user->fresh()->latitude)->toBeNull()->and($user->fresh()->longitude)->toBeNull();
});

test('without a place the member\'s own city comes first, then their country, then the best known', function () {
    [$user] = suggestedBy();
    $popular = Church::factory()->create(['name' => 'Popular Lira Church', 'city' => 'Lira']);
    Church::factory()->create(['name' => 'Quiet Lira Church', 'city' => 'Lira']);
    Church::factory()->create(['name' => 'Gulu Church', 'city' => 'Gulu']);
    Church::factory()->create(['name' => 'Nairobi Church', 'city' => 'Nairobi', 'country' => 'KE']);
    foreach (range(1, 3) as $i) {
        $popular->subscriptions()->create(['user_id' => member()->id, 'status' => 'active', 'subscribed_at' => now()]);
    }

    $answer = $this->postJson(route('api.v1.churches.suggest'))->assertOk()->json();
    $names = collect($answer['data'])->pluck('name')->all();

    expect($answer['located'])->toBeFalse()
        ->and(array_slice($names, 0, 2))->toBe(['Popular Lira Church', 'Quiet Lira Church'])
        ->and($answer['data'][0])->toMatchArray(['why' => 'In Lira', 'followers' => 3, 'distance_label' => null])
        ->and($names)->toContain('Gulu Church', 'Nairobi Church')
        ->and(array_search('Gulu Church', $names))->toBeLessThan(array_search('Nairobi Church', $names)); // same country before another country
});

test('churches already followed, and churches that are not active, are not suggested', function () {
    [$user] = suggestedBy();
    $followed = Church::factory()->create(['name' => 'Followed Church']);
    $followed->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'subscribed_at' => now()]);
    Church::factory()->pending()->create(['name' => 'Waiting Church']);
    Church::factory()->create(['name' => 'Open Church']);

    $answer = $this->postJson(route('api.v1.churches.suggest'))->assertOk()->json();

    expect(collect($answer['data'])->pluck('name')->all())->toBe(['Open Church'])->and($answer['following'])->toBe(1);
});

test('at most eight churches are suggested, and a place needs both numbers', function () {
    suggestedBy();
    Church::factory()->count(12)->create();

    expect($this->postJson(route('api.v1.churches.suggest'))->assertOk()->json('data'))->toHaveCount(8);
    $this->postJson(route('api.v1.churches.suggest'), ['latitude' => 2.2])->assertUnprocessable()->assertJsonValidationErrors('longitude');
    $this->postJson(route('api.v1.churches.suggest'), ['latitude' => 120, 'longitude' => 10])->assertUnprocessable();
});

test('only a signed-in member gets suggestions', function () {
    $this->postJson(route('api.v1.churches.suggest'))->assertUnauthorized();
});
