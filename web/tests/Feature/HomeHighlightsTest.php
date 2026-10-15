<?php

use App\Models\Church;
use App\Models\GivingChannel;
use App\Models\PrayerInteraction;
use App\Models\Quiz;
use App\Models\Sermon;
use App\Models\User;
use App\Services\ChurchOnboardingService;
use App\Services\PaymentService;
use App\Services\QuizService;
use App\Support\VerseOfTheDay;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

/*
| The home screen's extra sections: the verse of the day, the two most listened sermons, the prayer wall, the quiz
| games, and mobile money giving through ioTec Pay.
*/

function highlightChurch(string $name, ?User $member = null): Church
{
    $church = Church::factory()->create(['name' => $name, 'slug' => str($name)->slug()->toString()]);
    $member && $church->subscriptions()->create(['user_id' => $member->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);

    return $church;
}

function fakeBible(): void
{
    // Any chapter: forty verses, so whichever reference is picked for the day has words.
    $verses = collect(range(1, 40))->map(fn ($n) => ['verse' => $n, 'text' => "Verse {$n}:\n a few words of scripture."])->all();
    Http::fake(['bible-api.com/*' => Http::response(['verses' => $verses])]);
}

beforeEach(fn () => Cache::flush());

test('the verse of the day is the same for the whole day, in the member\'s translation', function () {
    fakeBible();
    $member = member();
    Sanctum::actingAs($member, ['member']);

    $verse = $this->getJson(route('api.v1.home'))->assertOk()->json('data.verse');
    expect($verse)->toHaveKeys(['reference', 'text', 'translation', 'book', 'chapter', 'verse'])
        ->and($verse['translation'])->toBe('KJV')->and($verse['text'])->not->toBeEmpty()
        // A verse range is shown with a typographic dash (2:8–9) while the list of references uses a plain hyphen.
        ->and(str_replace('–', '-', $verse['reference']))->toBe(VerseOfTheDay::reference());

    // Asked again, it comes from the day's cache, not from the Bible provider.
    Http::fake();
    expect($this->getJson(route('api.v1.home'))->json('data.verse.reference'))->toBe($verse['reference']);
    expect(VerseOfTheDay::reference(now()))->not->toBe(VerseOfTheDay::reference(now()->addDay()))->and(VerseOfTheDay::REFERENCES)->toHaveCount(56);
});

test('the home screen still loads when the Bible cannot be reached', function () {
    Http::fake(['bible-api.com/*' => Http::response('', 500)]);
    Sanctum::actingAs(member(), ['member']);

    $this->getJson(route('api.v1.home'))->assertOk()->assertJsonPath('data.verse', null);
});

test('the two most listened sermons of the member\'s churches come first, with their listens', function () {
    $member = member();
    Sanctum::actingAs($member, ['member']);
    $grace = highlightChurch('Grace Lira', $member);
    $other = highlightChurch('Not Followed');
    $make = fn (Church $c, string $title, int $listens) => tap($c->sermons()->create(['title' => $title, 'language' => 'en', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()->subDays(3)]), fn (Sermon $s) => $s->forceFill(['listens_count' => $listens])->save());
    $make($grace, 'Quiet one', 2);
    $make($grace, 'Most played', 90);
    $make($grace, 'Second most', 40);
    $make($other, 'Elsewhere hit', 500);

    $top = $this->getJson(route('api.v1.home'))->assertOk()->json('data.top_sermons');
    expect(collect($top)->pluck('title')->all())->toBe(['Most played', 'Second most'])->and($top[0]['listens'])->toBe(90)->and($top[0])->toHaveKeys(['id', 'church', 'thumbnail_url']);
});

test('playing a sermon counts one listen per member every half hour', function () {
    $member = member();
    Sanctum::actingAs($member, ['member']);
    $church = highlightChurch('Grace Lira', $member);
    $sermon = $church->sermons()->create(['title' => 'Hope', 'language' => 'en', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()]);

    $this->postJson(route('api.v1.sermons.listen', $sermon))->assertOk()->assertJson(['listens' => 1]);
    $this->postJson(route('api.v1.sermons.listen', $sermon))->assertOk()->assertJson(['listens' => 1]);
    expect(Sermon::find($sermon->id)->listens_count)->toBe(1)->and($sermon->views()->where('action', 'listen')->count())->toBe(1);

    // A members-only sermon cannot be counted by someone outside that church.
    $private = $church->sermons()->create(['title' => 'Members only', 'language' => 'en', 'status' => 'published', 'visibility' => 'members', 'published_at' => now()]);
    Sanctum::actingAs(member(), ['member']);
    $this->postJson(route('api.v1.sermons.listen', $private))->assertForbidden();
    expect(Sermon::find($private->id)->listens_count)->toBe(0);
});

test('the prayer wall shows the newest open requests and which ones the member prayed for', function () {
    $member = member();
    Sanctum::actingAs($member, ['member']);
    $church = highlightChurch('Grace Lira', $member);
    $author = member();
    $first = $church->prayerRequests()->create(['user_id' => $author->id, 'body' => 'Pray for my exams.', 'visibility' => 'church', 'status' => 'open']);
    $church->prayerRequests()->create(['user_id' => $author->id, 'body' => 'Pray for healing.', 'visibility' => 'church', 'status' => 'open', 'created_at' => now()->addMinute()]);
    $church->prayerRequests()->create(['user_id' => $author->id, 'body' => 'Already answered.', 'visibility' => 'church', 'status' => 'answered']);
    $church->prayerRequests()->create(['user_id' => $author->id, 'body' => 'Waiting for approval.', 'visibility' => 'church', 'status' => 'pending']);
    PrayerInteraction::create(['church_id' => $church->id, 'prayer_request_id' => $first->id, 'user_id' => $member->id, 'type' => 'prayed']);

    $prayers = $this->getJson(route('api.v1.home'))->assertOk()->json('data.prayers');
    expect($prayers['open_count'])->toBe(2)->and(collect($prayers['items'])->pluck('body')->all())->toBe(['Pray for healing.', 'Pray for my exams.'])
        ->and(collect($prayers['items'])->pluck('prayed')->all())->toBe([false, true]);

    // The prayer wall itself marks every request, not just the ones before the first unprayed one.
    expect(collect($this->getJson(route('api.v1.prayer.index'))->assertOk()->json('data'))->pluck('prayed')->all())->toBe([false, true]);
});

test('a live quiz game and the member\'s own record are on the home screen', function () {
    $member = member();
    Sanctum::actingAs($member, ['member']);
    $church = highlightChurch('Grace Lira', $member);
    $host = member();
    $quiz = Quiz::create(['church_id' => $church->id, 'user_id' => $host->id, 'title' => 'Bible Basics']);
    $quiz->questions()->create(['position' => 0, 'question' => 'Who built the ark?', 'options' => ['Moses', 'Noah', 'Abraham', 'David'], 'correct_option' => 1, 'time_limit' => 10, 'points' => 1000]);
    app(QuizService::class)->host($quiz, $host);

    $quizData = $this->getJson(route('api.v1.home'))->assertOk()->json('data.quiz');
    expect($quizData['live'])->toHaveCount(1)->and($quizData['live'][0]['quiz'])->toBe('Bible Basics')->and($quizData['live'][0])->not->toHaveKey('pin')
        ->and($quizData['played'])->toBe(0)->and($quizData['best_score'])->toBe(0);
});

// ── Giving through ioTec Pay ────────────────────────────────────────────────────────────────────────────

function ioChurch(User $member): Church
{
    $church = highlightChurch('Grace Lira', $member);
    foreach (['iotec' => 'Mobile money', 'mtn_momo' => 'MTN MoMo', 'airtel_money' => 'Airtel Money', 'stripe' => 'Card'] as $i => $label) {
        $church->givingChannels()->create(['type' => $i, 'label' => $label, 'is_active' => true, 'sort' => 0]);
    }

    return $church;
}

test('with ioTec Pay carrying mobile money, the app offers one Mobile money button and no pretend gateways', function () {
    config(['faithfellows.payments.sandbox' => false, 'services.iotec.client_id' => 'id', 'services.iotec.client_secret' => 'secret', 'services.iotec.wallet_id' => 'wallet', 'services.iotec.sandbox' => false]);
    PaymentService::flush();
    $member = member();
    Sanctum::actingAs($member, ['member']);
    $church = ioChurch($member);

    $methods = $this->getJson(route('api.v1.giving.options', $church))->assertOk()->json('data.methods');
    // Card has no credentials here, so in real-money mode it would only pretend to take a payment: it is not offered.
    expect(collect($methods)->pluck('type')->all())->toBe(['iotec'])->and($methods[0])->toMatchArray(['label' => 'Mobile money', 'needs_phone' => true]);
});

test('without ioTec the church\'s own MTN and Airtel buttons stay', function () {
    config(['faithfellows.payments.sandbox' => true, 'services.iotec.client_id' => '', 'services.iotec.client_secret' => '']);
    PaymentService::flush();
    $member = member();
    Sanctum::actingAs($member, ['member']);
    $church = ioChurch($member);

    expect(collect($this->getJson(route('api.v1.giving.options', $church))->json('data.methods'))->pluck('type')->all())->toBe(['iotec', 'mtn_momo', 'airtel_money', 'stripe']);
});

test('a newly approved church can take mobile money from its first day, at the top of its list', function () {
    $church = Church::factory()->create(['status' => 'pending']);
    $church->givingChannels()->create(['type' => 'cash', 'label' => 'Cash', 'is_active' => true, 'sort' => 0]);

    app(ChurchOnboardingService::class)->approve($church, notify: false);
    app(ChurchOnboardingService::class)->approve($church->fresh(), notify: false); // approving twice adds nothing more

    $channels = GivingChannel::where('church_id', $church->id)->orderBy('sort')->get();
    expect($channels->pluck('type')->all())->toBe(['iotec', 'cash'])->and($channels[0]->is_active)->toBeTrue();
});
