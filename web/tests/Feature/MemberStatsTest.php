<?php

use App\Models\Church;
use App\Models\MemberActivity;
use App\Models\Note;
use App\Services\MemberStats;
use Laravel\Sanctum\Sanctum;

/*
| "How am I doing?": the app reports small pieces of activity (minutes with the Bible, minutes listening, things shared), and the
| profile shows them added up for the week or month with progress rings, a streak, other figures and what is missing from the profile.
*/

function statsMember(array $attributes = [])
{
    $user = member(array_merge(['timezone' => 'Africa/Kampala', 'phone' => null], $attributes));
    Sanctum::actingAs($user, ['member']);

    return $user;
}

test('the app reports activity and it is added up for the day', function () {
    $user = statsMember();

    $this->postJson(route('api.v1.me.activity'), ['events' => [
        ['kind' => 'read', 'seconds' => 300],
        ['kind' => 'read', 'seconds' => 120],
        ['kind' => 'chapters', 'count' => 2],
        ['kind' => 'radio', 'seconds' => 600],
        ['kind' => 'share_scripture'],
    ]])->assertOk()->assertJson(['recorded' => 5]);

    $today = now('Africa/Kampala')->toDateString();
    expect(MemberActivity::where('user_id', $user->id)->where('day', $today)->where('kind', 'read')->value('seconds'))->toBe(420)
        ->and(MemberActivity::where('user_id', $user->id)->where('kind', 'chapters')->value('count'))->toBe(2)
        ->and(MemberActivity::where('user_id', $user->id)->where('kind', 'share_scripture')->value('count'))->toBe(1);
});

test('unknown kinds, absurd times, future days and very old days are not counted', function () {
    $user = statsMember();

    $this->postJson(route('api.v1.me.activity'), ['events' => [['kind' => 'hacking', 'seconds' => 10]]])->assertUnprocessable();
    $this->postJson(route('api.v1.me.activity'), ['events' => [['kind' => 'read', 'seconds' => 999999]]])->assertUnprocessable();

    $future = now('Africa/Kampala')->addDays(2)->toDateString();
    $old = now('Africa/Kampala')->subDays(30)->toDateString();
    $this->postJson(route('api.v1.me.activity'), ['events' => [
        ['kind' => 'read', 'seconds' => 60, 'day' => $future],
        ['kind' => 'read', 'seconds' => 60, 'day' => $old],
        ['kind' => 'read', 'seconds' => 60, 'day' => now('Africa/Kampala')->subDays(2)->toDateString()],
    ]])->assertOk()->assertJson(['recorded' => 1]);
    expect(MemberActivity::where('user_id', $user->id)->count())->toBe(1);

    $this->getJson(route('api.v1.me.stats', ['period' => 'decade']))->assertUnprocessable();
});

test('the week adds up minutes, fills the rings against gentle goals, and counts the streak', function () {
    $user = statsMember();
    $now = now('Africa/Kampala')->startOfDay();
    $put = fn (int $daysAgo, string $kind, int $amount) => MemberActivity::create(['user_id' => $user->id, 'day' => $now->copy()->subDays($daysAgo)->toDateString(), 'kind' => $kind, ($kind === 'read' || $kind === 'radio' || $kind === 'podcast') ? 'seconds' : 'count' => $amount]);

    // Today and the two days before it: a streak of three.
    foreach ([0, 1, 2] as $d) {
        $put($d, 'open', 1);
    }
    $put(0, 'read', 35 * 60);          // 35 of 70 minutes: half the Bible ring
    $put(1, 'radio', 20 * 60);
    $put(1, 'podcast', 10 * 60);       // 30 of 60 minutes listening
    $put(0, 'share_scripture', 2);
    $put(0, 'share_inspiration', 1);   // 3 of 5 shared
    $put(0, 'chapters', 4);

    // The start of the week only counts what happened since Monday, so keep the checks to what is certainly in this week.
    $summary = app(MemberStats::class)->summary($user, 'month');

    expect($summary['totals'])->toMatchArray(['read_minutes' => 35, 'radio_minutes' => 20, 'podcast_minutes' => 10, 'listen_minutes' => 30, 'shared_scriptures' => 2, 'shared_inspirations' => 1, 'shares' => 3, 'chapters' => 4])
        ->and($summary['streak'])->toBe(['current' => 3, 'best' => 3])
        ->and(collect($summary['rings'])->pluck('key')->all())->toBe(['bible', 'listening', 'sharing'])
        ->and($summary['week'])->toHaveCount(7)->and(collect($summary['week'])->last()['read_minutes'])->toBe(35);

    $weekly = app(MemberStats::class)->summary($user, 'week');
    expect(collect($weekly['rings'])->firstWhere('key', 'bible')['goal'])->toBe(70);
});

test('a streak survives until the day is over, and breaks after a missed day', function () {
    $user = statsMember();
    $now = now('Africa/Kampala')->startOfDay();
    foreach ([1, 2, 3] as $d) { // yesterday and the two days before; nothing yet today
        MemberActivity::create(['user_id' => $user->id, 'day' => $now->copy()->subDays($d)->toDateString(), 'kind' => 'open', 'count' => 1]);
    }
    expect(app(MemberStats::class)->summary($user)['streak'])->toBe(['current' => 3, 'best' => 3]);

    MemberActivity::query()->where('user_id', $user->id)->delete();
    foreach ([2, 3] as $d) { // a missed day yesterday
        MemberActivity::create(['user_id' => $user->id, 'day' => $now->copy()->subDays($d)->toDateString(), 'kind' => 'open', 'count' => 1]);
    }
    expect(app(MemberStats::class)->summary($user)['streak'])->toBe(['current' => 0, 'best' => 2]);
});

test('the profile says what is still missing, starting with the phone number used for giving', function () {
    $user = statsMember();

    $profile = $this->getJson(route('api.v1.me.stats'))->assertOk()->json('data.profile');
    expect($profile['phone_missing'])->toBeTrue()->and(collect($profile['missing'])->pluck('key')->first())->toBe('phone')
        ->and($profile['percent'])->toBeLessThan(100);

    $church = Church::factory()->create();
    $church->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'subscribed_at' => now()]);
    $user->flushSubscriptionCache(); // the test reuses one user object for both requests
    $this->patchJson(route('api.v1.me.update'), ['phone' => '0772123456', 'city' => 'Lira', 'bio' => 'I love worship.', 'date_of_birth' => '1990-05-05', 'interests' => ['Worship']])->assertOk();

    $after = $this->getJson(route('api.v1.me.stats'))->assertOk()->json('data.profile');
    expect($after['phone_missing'])->toBeFalse()->and(collect($after['missing'])->pluck('key')->all())->toBe(['photo'])->and($after['percent'])->toBe(85);
});

test('the summary also carries the rest of what the member did, and their latest notes', function () {
    $user = statsMember();
    Note::create(['user_id' => $user->id, 'context' => 'note:1', 'title' => 'Grace', 'body' => 'Grace is a gift.', 'color' => 'rose']);

    $data = $this->getJson(route('api.v1.me.stats'))->assertOk()->json('data');

    expect($data['other'])->toHaveKeys(['highlights', 'notes', 'games_played', 'best_score', 'prayers', 'given_year', 'currency', 'churches', 'connections', 'member_since'])
        ->and($data['other']['notes'])->toBe(1)->and($data['notes'])->toHaveCount(1)->and($data['notes'][0]['body'])->toBe('Grace is a gift.');
});

test('the giving summary lists the churches the member follows, so the Give tab never asks them to follow one again', function () {
    $user = statsMember();
    $church = Church::factory()->create(['name' => 'Grace Lira']);
    $church->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'subscribed_at' => now()]);
    Church::factory()->create(['name' => 'Not followed']);

    $churches = collect($this->getJson(route('api.v1.giving.summary'))->assertOk()->json('data.churches'));

    expect($churches->pluck('name')->all())->toBe(['Grace Lira'])->and($churches[0])->toHaveKeys(['id', 'slug', 'name', 'logo_url']);
});
