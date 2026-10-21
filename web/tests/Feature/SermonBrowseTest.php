<?php

use App\Models\Church;
use App\Models\Sermon;
use App\Models\SermonSeries;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/*
| The Sermons page of the app: a banner of sermons by different preachers, the preachers, the newest, the most listened and the series, in one request,
| limited to what the member may see.
*/

function browseChurch(User $member): Church
{
    $church = Church::factory()->create();
    $church->subscriptions()->create(['user_id' => $member->id, 'status' => 'active', 'subscribed_at' => now()]);

    return $church;
}

function browseSermon(Church $church, string $title, array $overrides = []): Sermon
{
    $listens = $overrides['listens_count'] ?? 0;   // a counter, not something a form fills in
    unset($overrides['listens_count']);
    $sermon = $church->sermons()->create(array_merge(['title' => $title, 'language' => 'en', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()->subDay()], $overrides));

    $sermon->forceFill(['listens_count' => $listens])->save();

    return $sermon;
}

test('the Sermons page comes in one request: banner, preachers, newest, most listened and series', function () {
    $member = member();
    $church = browseChurch($member);
    $series = SermonSeries::create(['church_id' => $church->id, 'title' => 'Walking in Faith']);
    browseSermon($church, 'Faith one', ['preacher' => 'Pastor Okello', 'listens_count' => 40, 'sermon_series_id' => $series->id, 'published_at' => now()->subDays(5)]);
    browseSermon($church, 'Faith two', ['preacher' => 'Pastor Okello', 'listens_count' => 10, 'sermon_series_id' => $series->id, 'published_at' => now()->subDays(4)]);
    browseSermon($church, 'Grace', ['preacher' => 'Rev. Akello', 'listens_count' => 25, 'published_at' => now()->subDays(3)]);
    browseSermon($church, 'Hope', ['preacher' => 'Bishop Opio', 'published_at' => now()->subDay()]);
    browseSermon($church, 'Unnamed word', ['preacher' => null, 'published_at' => now()->subHours(2)]);
    Sanctum::actingAs($member, ['member']);

    $data = $this->getJson(route('api.v1.sermons.browse'))->assertOk()->json('data');

    // The banner has one sermon per preacher, the best known first.
    expect(collect($data['featured'])->pluck('preacher')->all())->toBe(['Pastor Okello', 'Rev. Akello', 'Bishop Opio'])
        ->and($data['featured'][0]['title'])->toBe('Faith one')
        ->and(collect($data['preachers'])->pluck('name')->all())->toBe(['Pastor Okello', 'Bishop Opio', 'Rev. Akello'])
        ->and($data['preachers'][0]['sermons'])->toBe(2)
        ->and($data['latest'][0]['title'])->toBe('Unnamed word')
        ->and(collect($data['popular'])->pluck('title')->all())->toBe(['Faith one', 'Grace', 'Faith two'])
        ->and($data['popular'][0]['listens'])->toBe(40)
        ->and($data['series'])->toHaveCount(1)->and($data['series'][0])->toMatchArray(['title' => 'Walking in Faith', 'sermons' => 2])
        ->and($data['total'])->toBe(5);
});

test('the banner prefers sermons with a real picture over the generated cover', function () {
    Storage::fake('public');
    $member = member();
    $church = browseChurch($member);
    browseSermon($church, 'Plain cover', ['preacher' => 'Pastor A', 'listens_count' => 90]);
    $withPhoto = browseSermon($church, 'With a photo', ['preacher' => 'Pastor B', 'listens_count' => 1]);
    $withPhoto->addMedia(UploadedFile::fake()->image('preacher.jpg', 800, 450))->toMediaCollection('thumbnail');
    Sanctum::actingAs($member, ['member']);

    $featured = $this->getJson(route('api.v1.sermons.browse'))->assertOk()->json('data.featured');

    expect(collect($featured)->pluck('title')->all())->toBe(['With a photo', 'Plain cover']);
});

test('the Sermons page shows only what this member may see', function () {
    $member = member();
    $church = browseChurch($member);
    $other = Church::factory()->create();
    browseSermon($church, 'Mine', ['preacher' => 'Pastor Okello']);
    browseSermon($other, 'Members only elsewhere', ['preacher' => 'Pastor Secret', 'visibility' => 'members']);
    browseSermon($church, 'Not out yet', ['preacher' => 'Pastor Later', 'published_at' => now()->addDay()]);
    browseSermon($church, 'A draft', ['preacher' => 'Pastor Draft', 'status' => 'draft']);
    Sanctum::actingAs($member, ['member']);

    $data = $this->getJson(route('api.v1.sermons.browse'))->assertOk()->json('data');

    expect(collect($data['featured'])->pluck('title')->all())->toBe(['Mine'])
        ->and(collect($data['preachers'])->pluck('name')->all())->toBe(['Pastor Okello'])
        ->and($data['total'])->toBe(1);
});

test('someone who follows no church gets empty sections, not an error', function () {
    Sanctum::actingAs(member(), ['member']);

    $this->getJson(route('api.v1.sermons.browse'))->assertOk()
        ->assertJsonPath('data.featured', [])->assertJsonPath('data.preachers', [])->assertJsonPath('data.series', [])->assertJsonPath('data.total', 0);
});

test('the list can be narrowed to one preacher or one series, and the page size is respected', function () {
    $member = member();
    $church = browseChurch($member);
    $series = SermonSeries::create(['church_id' => $church->id, 'title' => 'Grace Series']);
    foreach (range(1, 5) as $i) {
        browseSermon($church, "Okello {$i}", ['preacher' => 'Pastor Okello', 'sermon_series_id' => $i <= 2 ? $series->id : null, 'published_at' => now()->subDays($i)]);
    }
    browseSermon($church, 'Akello 1', ['preacher' => 'Rev. Akello']);
    Sanctum::actingAs($member, ['member']);

    expect($this->getJson(route('api.v1.sermons.index', ['preacher' => 'Rev. Akello']))->json('data.*.title'))->toBe(['Akello 1'])
        ->and($this->getJson(route('api.v1.sermons.index', ['series' => $series->id]))->json('data.*.title'))->toBe(['Okello 1', 'Okello 2'])
        ->and($this->getJson(route('api.v1.sermons.index', ['per_page' => 2]))->json('data'))->toHaveCount(2)
        ->and($this->getJson(route('api.v1.sermons.index', ['per_page' => 500]))->json('meta.per_page'))->toBe(50);
});

test('a banner with fewer than three named preachers is filled up with the newest sermons', function () {
    $member = member();
    $church = browseChurch($member);
    browseSermon($church, 'Named', ['preacher' => 'Pastor Okello', 'listens_count' => 5, 'published_at' => now()->subDays(3)]);
    browseSermon($church, 'Newest unnamed', ['published_at' => now()->subHour()]);
    browseSermon($church, 'Older unnamed', ['published_at' => now()->subDays(2)]);
    browseSermon($church, 'Oldest unnamed', ['published_at' => now()->subDays(9)]);
    Sanctum::actingAs($member, ['member']);

    expect($this->getJson(route('api.v1.sermons.browse'))->json('data.featured.*.title'))->toBe(['Named', 'Newest unnamed', 'Older unnamed']);
});
