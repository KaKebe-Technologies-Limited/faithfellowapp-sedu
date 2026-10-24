<?php

use App\Models\Church;
use Laravel\Sanctum\Sanctum;

/*
| "Grow your circle" puts the people who follow the same church first, wherever they live: a member in London finds the other members in London who follow the
| same church in Kampala, then the members of that church in Uganda, before anyone who only shares an interest.
*/

function churchmate(array $attributes = [], ?Church $church = null)
{
    $user = member(array_merge(['directory_opt_in' => true, 'open_to_connect' => false, 'date_of_birth' => now()->subYears(32)->toDateString(), 'city' => 'London', 'country' => 'GB', 'interests' => []], $attributes));
    $church?->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'subscribed_at' => now()]);

    return $user;
}

test('members who follow the same church come first, and those abroad together say so', function () {
    $church = Church::factory()->create(['name' => 'Grace Church Kampala', 'country' => 'UG']);
    $me = churchmate(['name' => 'Me In London', 'interests' => ['Music']], $church);
    $together = churchmate(['name' => 'Also In London'], $church);
    $athome = churchmate(['name' => 'Kampala Home', 'city' => 'Kampala', 'country' => 'UG'], $church);
    // Shares an interest with me and is open to anyone, but follows no church of mine.
    $stranger = churchmate(['name' => 'Interest Only', 'open_to_connect' => true, 'interests' => ['Music']]);
    $hidden = churchmate(['name' => 'Hidden Churchmate', 'directory_opt_in' => false], $church);

    Sanctum::actingAs($me, ['member']);
    $people = collect($this->getJson(route('api.v1.people.index', ['scope' => 'recommended']))->assertOk()->json('data'));

    expect($people->pluck('name')->all())->toBe(['Also In London', 'Kampala Home', 'Interest Only'])
        ->and(array_slice($people[0]['reasons'], 0, 2))->toBe(['Follows Grace Church Kampala too', 'Also in United Kingdom'])
        ->and($people[1]['reasons'][0])->toBe('Follows Grace Church Kampala too')
        ->and($people[1]['reasons'])->not->toContain('Also in United Kingdom')
        ->and($people[0]['shared_churches'][0]['name'])->toBe('Grace Church Kampala')
        ->and($people[2]['reasons'][0])->toBe('Both like Music');
});

test('a person who follows several of the same churches says so in one line', function () {
    $first = Church::factory()->create(['name' => 'First Church', 'country' => 'UG']);
    $second = Church::factory()->create(['name' => 'Second Church', 'country' => 'UG']);
    $me = churchmate([], $first);
    $second->subscriptions()->create(['user_id' => $me->id, 'status' => 'active', 'subscribed_at' => now()]);
    $both = churchmate(['name' => 'Follows Both'], $first);
    $second->subscriptions()->create(['user_id' => $both->id, 'status' => 'active', 'subscribed_at' => now()]);

    Sanctum::actingAs($me, ['member']);
    $reasons = $this->getJson(route('api.v1.people.index', ['scope' => 'recommended']))->assertOk()->json('data.0.reasons');

    expect($reasons[0])->toMatch('/^Follows (First|Second) Church and 1 more of your churches$/');
});
