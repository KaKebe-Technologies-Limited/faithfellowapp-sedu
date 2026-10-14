<?php

use App\Models\Church;
use App\Models\Podcast;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/*
| Grow your circle: "For you" ranks the people a member may find by what they share (interests, podcasts, age, town…), and
| churches near the member are listed nearest first from the place they shared.
*/

function circleUser(array $attributes = [])
{
    return member(array_merge(['open_to_connect' => true, 'date_of_birth' => now()->subYears(30)->toDateString(), 'city' => 'Lira', 'country' => 'UG', 'interests' => []], $attributes));
}

test('For you puts the people with the most in common first and says why', function () {
    $me = circleUser(['interests' => ['Music', 'Prayer'], 'latitude' => 2.25, 'longitude' => 32.9]);
    $podcast = Podcast::create(['user_id' => $me->id, 'name' => 'Morning Word', 'slug' => 'morning-word', 'host_name' => 'Ruth', 'summary' => 'A word every day.', 'category' => 'devotional', 'status' => 'approved', 'approved_at' => now()]);
    DB::table('podcast_follows')->insert([['podcast_id' => $podcast->id, 'user_id' => $me->id, 'created_at' => now(), 'updated_at' => now()]]);

    $alike = circleUser(['name' => 'Alike Person', 'interests' => ['Music', 'Prayer', 'Youth'], 'date_of_birth' => now()->subYears(32)->toDateString(), 'latitude' => 2.26, 'longitude' => 32.91]);
    DB::table('podcast_follows')->insert([['podcast_id' => $podcast->id, 'user_id' => $alike->id, 'created_at' => now(), 'updated_at' => now()]]);
    $partly = circleUser(['name' => 'Partly Person', 'interests' => ['Music'], 'city' => 'Gulu', 'date_of_birth' => now()->subYears(55)->toDateString()]);
    $nothing = circleUser(['name' => 'Aardvark Nothing', 'interests' => ['Missions'], 'city' => 'Kampala', 'country' => 'KE', 'date_of_birth' => now()->subYears(60)->toDateString()]);
    $hidden = circleUser(['name' => 'Hidden Person', 'open_to_connect' => false, 'directory_opt_in' => false]);
    $minor = circleUser(['name' => 'Too Young', 'date_of_birth' => now()->subYears(15)->toDateString()]);

    Sanctum::actingAs($me, ['member']);
    $people = collect($this->getJson(route('api.v1.people.index', ['scope' => 'recommended']))->assertOk()->json('data'));

    expect($people->pluck('name')->all())->toBe(['Alike Person', 'Partly Person', 'Aardvark Nothing'])
        ->and($people[0]['reasons'])->toContain('Follow the same podcasts', 'Similar age', 'Same town', 'Lives near you')
        ->and($people[0]['reasons'][0])->toContain('Music')
        ->and($people[1]['reasons'])->toContain('Both like Music')
        ->and($people[2])->not->toHaveKey('reasons');
});

test('the list of people is paged and the other scopes still work', function () {
    $me = circleUser();
    foreach (range(1, 23) as $n) {
        circleUser(['name' => sprintf('Person %02d', $n)]);
    }
    Sanctum::actingAs($me, ['member']);

    $first = $this->getJson(route('api.v1.people.index', ['scope' => 'recommended']))->assertOk();
    $second = $this->getJson(route('api.v1.people.index', ['scope' => 'recommended', 'page' => 2]))->assertOk();
    expect($first->json('data'))->toHaveCount(20)->and($first->json('meta.total'))->toBe(23)->and($second->json('data'))->toHaveCount(3);

    $this->getJson(route('api.v1.people.index', ['scope' => 'everyone']))->assertOk()->assertJsonCount(20, 'data');
});

test('churches near the member are listed nearest first within the chosen distance', function () {
    $me = circleUser(['latitude' => 2.25, 'longitude' => 32.9]);
    $near = Church::factory()->create(['name' => 'Near Church', 'lat' => 2.26, 'lng' => 32.91]);
    $middle = Church::factory()->create(['name' => 'Middle Church', 'lat' => 2.45, 'lng' => 32.9]);
    Church::factory()->create(['name' => 'Far Church', 'lat' => 0.35, 'lng' => 32.58]);
    Church::factory()->create(['name' => 'Nowhere Church', 'lat' => null, 'lng' => null]);

    Sanctum::actingAs($me, ['member']);
    $wide = $this->getJson(route('api.v1.churches.nearby', ['radius' => 50]))->assertOk()->json();
    expect($wide['has_location'])->toBeTrue()->and(collect($wide['data'])->pluck('name')->all())->toBe(['Near Church', 'Middle Church'])
        ->and($wide['data'][0]['distance_label'])->toContain('km')->and($wide['data'][0]['following'])->toBeFalse();

    $narrow = $this->getJson(route('api.v1.churches.nearby', ['radius' => 5]))->assertOk()->json('data');
    expect(collect($narrow)->pluck('name')->all())->toBe(['Near Church']);

    $this->getJson(route('api.v1.churches.nearby', ['radius' => 7]))->assertUnprocessable();
});

test('without a shared place there are no nearby churches yet, and the answer says so', function () {
    Sanctum::actingAs(circleUser(), ['member']);

    $this->getJson(route('api.v1.churches.nearby'))->assertOk()->assertJson(['has_location' => false, 'data' => []]);
    $this->getJson('/api/v1/churches/nearby')->assertOk();
});
