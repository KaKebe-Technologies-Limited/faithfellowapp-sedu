<?php

use App\Models\Church;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/*
| Finding believers by church or by distance. A member chooses to share an approximate place; others only ever see
| "about 10 km away", never coordinates.
*/

function nearChurch(): Church
{
    return Church::factory()->create();
}

function nearMember(array $attributes = [], ?Church $church = null): User
{
    $user = member(array_merge(['date_of_birth' => now()->subYears(30)->toDateString(), 'open_to_connect' => true], $attributes));
    $church?->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);

    return $user;
}

// Lira town, a point about 3 km away, Gulu (about 80 km north), Kampala (about 215 km south).
const LIRA = [2.25, 32.90];
const NEAR_LIRA = [2.27, 32.92];
const GULU = [2.77, 32.30];
const KAMPALA = [0.35, 32.61];

function place(User $user, array $point): User
{
    $user->forceFill(['latitude' => $point[0], 'longitude' => $point[1]])->save();

    return $user;
}

test('a member shares an approximate place, which is rounded and can be removed', function () {
    $ann = nearMember();
    Sanctum::actingAs($ann, ['member']);
    expect($this->getJson(route('api.v1.me'))->json('data.has_location'))->toBeFalse();

    $this->putJson(route('api.v1.me.location'), ['latitude' => 2.24756, 'longitude' => 32.90123])->assertOk()->assertJsonPath('data.has_location', true);
    expect((float) $ann->fresh()->latitude)->toBe(2.25)->and((float) $ann->fresh()->longitude)->toBe(32.9);

    $this->putJson(route('api.v1.me.location'), ['latitude' => 120, 'longitude' => 32])->assertUnprocessable();
    $this->deleteJson(route('api.v1.me.location.remove'))->assertOk()->assertJsonPath('data.has_location', false);
    expect($ann->fresh()->latitude)->toBeNull();
});

test('near me lists the closest believers first, within the chosen distance, with only a rough distance', function () {
    $ann = place(nearMember(['name' => 'Ann']), LIRA);
    $bob = place(nearMember(['name' => 'Bob Near']), NEAR_LIRA);
    $carol = place(nearMember(['name' => 'Carol Gulu']), GULU);
    $erin = place(nearMember(['name' => 'Erin Kampala']), KAMPALA);
    nearMember(['name' => 'Dan No Place']);
    nearMember(['name' => 'Hidden Hilda', 'open_to_connect' => false]);
    Sanctum::actingAs($ann, ['member']);

    $close = $this->getJson(route('api.v1.people.index', ['scope' => 'nearby', 'radius' => 10]))->assertOk();
    expect(collect($close->json('data'))->pluck('name')->all())->toBe(['Bob Near'])
        ->and($close->json('data.0.distance_label'))->toBe('Less than 5 km away')
        ->and($close->json('viewer.has_location'))->toBeTrue()
        ->and($close->json('data.0'))->not->toHaveKeys(['latitude', 'longitude', 'phone', 'email']);

    $wider = $this->getJson(route('api.v1.people.index', ['scope' => 'nearby', 'radius' => 100]))->assertOk()->json('data');
    expect(collect($wider)->pluck('name')->all())->toBe(['Bob Near', 'Carol Gulu'])
        ->and($wider[1]['distance_label'])->toMatch('/^About (7|8|9)0 km away$/');

    $far = $this->getJson(route('api.v1.people.index', ['scope' => 'nearby', 'radius' => 200]))->json('data');
    expect(collect($far)->pluck('name')->all())->toBe(['Bob Near', 'Carol Gulu']); // Kampala is a little over 200 km
    expect($erin->name)->toBe('Erin Kampala');
});

test('near me asks for a place first when you have not shared one', function () {
    place(nearMember(['name' => 'Bob']), NEAR_LIRA);
    $ann = nearMember(['name' => 'Ann']);
    Sanctum::actingAs($ann, ['member']);

    $response = $this->getJson(route('api.v1.people.index', ['scope' => 'nearby']))->assertOk();
    expect($response->json('data'))->toBe([])->and($response->json('viewer.has_location'))->toBeFalse();
});

test('my church shows only members of my churches who chose to be listed, and everyone shows the rest', function () {
    $church = nearChurch();
    $ann = nearMember(['name' => 'Ann', 'open_to_connect' => false, 'directory_opt_in' => true], $church);
    nearMember(['name' => 'Bob In Church', 'open_to_connect' => false, 'directory_opt_in' => true], $church);
    nearMember(['name' => 'Cara Hidden In Church', 'open_to_connect' => false, 'directory_opt_in' => false], $church);
    nearMember(['name' => 'Dan Elsewhere']);
    Sanctum::actingAs($ann, ['member']);

    expect(collect($this->getJson(route('api.v1.people.index', ['scope' => 'church']))->json('data'))->pluck('name')->all())->toBe(['Bob In Church'])
        ->and(collect($this->getJson(route('api.v1.people.index', ['scope' => 'everyone']))->json('data'))->pluck('name')->sort()->values()->all())->toBe(['Bob In Church', 'Dan Elsewhere']);
});
