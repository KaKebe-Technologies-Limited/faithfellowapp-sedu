<?php

use App\Models\Church;
use App\Models\Inspiration;
use App\Models\Podcast;
use App\Services\InspirationService;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

/*
| The home screen: an inspiring message for the part of the day, the slider of highlights with pictures, the top four podcasts, and
| sermons that even a brand-new sign-up sees. The super admin looks after the messages.
*/

test('the day has three parts and each shows one message from its own library, a new one every day', function () {
    $service = app(InspirationService::class);

    expect($service->slotAt(Carbon::parse('2026-10-14 05:00')))->toBe('morning')->and($service->slotAt(Carbon::parse('2026-10-14 09:59')))->toBe('morning')
        ->and($service->slotAt(Carbon::parse('2026-10-14 10:00')))->toBe('midmorning')->and($service->slotAt(Carbon::parse('2026-10-14 15:59')))->toBe('midmorning')
        ->and($service->slotAt(Carbon::parse('2026-10-14 16:00')))->toBe('evening')->and($service->slotAt(Carbon::parse('2026-10-14 03:00')))->toBe('evening');

    expect(Inspiration::count())->toBeGreaterThanOrEqual(60);
    $today = $service->current(Carbon::parse('2026-10-14 07:30'));
    $tomorrow = $service->current(Carbon::parse('2026-10-15 07:30'));
    expect($today)->toHaveKeys(['slot', 'label', 'body', 'reference'])->and($today['slot'])->toBe('morning')->and($today['label'])->toBe('Morning')
        ->and($tomorrow['body'])->not->toBe($today['body'])->and($service->current(Carbon::parse('2026-10-14 07:31'))['body'])->toBe($today['body']);

    Inspiration::where('slot', 'evening')->update(['is_active' => false]);
    expect($service->current(Carbon::parse('2026-10-14 20:00')))->toBeNull();
});

test('the home screen carries the inspiration, highlights with pictures, the top podcasts and sermons', function () {
    $church = Church::factory()->create(['status' => 'active']);
    $member = member();
    $church->subscriptions()->create(['user_id' => $member->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);
    $church->events()->create(['title' => 'Youth Night', 'slug' => 'youth-night', 'description' => 'Music and a message', 'location_name' => 'Main hall', 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHours(2), 'status' => 'published', 'visibility' => 'public']);
    foreach (range(1, 6) as $n) {
        $podcast = Podcast::create(['name' => "Podcast {$n}", 'slug' => "podcast-{$n}", 'host_name' => 'Host', 'summary' => 'About it', 'category' => 'devotional', 'status' => 'approved', 'user_id' => $member->id]);
        $podcast->forceFill(['followers_count' => $n])->save();
    }
    Sanctum::actingAs($member, ['member']);

    $data = $this->getJson(route('api.v1.home'))->assertOk()->json('data');

    expect($data['inspiration'])->toHaveKeys(['slot', 'label', 'body'])
        ->and(collect($data['podcasts'])->pluck('name')->all())->toBe(['Podcast 6', 'Podcast 5', 'Podcast 4', 'Podcast 3'])
        ->and($data['highlights'])->not->toBeEmpty();
    $event = collect($data['highlights'])->firstWhere('kind', 'event');
    expect($event)->toMatchArray(['title' => 'Youth Night', 'label' => 'Event'])->and($event['image'])->toStartWith('http')->and($event['subtitle'])->toContain('Main hall');
});

test('a brand-new sign-up with no church still sees public sermons and the inspiration', function () {
    $church = Church::factory()->create(['status' => 'active']);
    $church->sermons()->create(['title' => 'Public message', 'preacher' => 'Pastor', 'description' => 'x', 'status' => 'published', 'visibility' => 'public', 'published_at' => now()->subDay()]);
    $church->sermons()->create(['title' => 'Members only', 'preacher' => 'Pastor', 'description' => 'x', 'status' => 'published', 'visibility' => 'members', 'published_at' => now()->subDay()]);
    Sanctum::actingAs(member(), ['member']);

    $data = $this->getJson(route('api.v1.home'))->assertOk()->json('data');

    expect(collect($data['sermons'])->pluck('title')->all())->toBe(['Public message'])->and($data['inspiration'])->not->toBeNull();
});

test('the super admin adds, changes, switches off and removes inspiring messages', function () {
    $admin = superAdmin();
    $this->actingAs(member())->get(route('super-admin.inspiration.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('super-admin.inspiration.index'))->assertOk()->assertSee('Inspiration');

    $this->actingAs($admin)->post(route('super-admin.inspiration.store'), ['slot' => 'evening', 'body' => 'Rest in Him tonight.', 'reference' => ''])->assertRedirect();
    $message = Inspiration::where('body', 'Rest in Him tonight.')->sole();
    expect($message->is_active)->toBeTrue()->and($message->slot)->toBe('evening');

    $this->actingAs($admin)->put(route('super-admin.inspiration.update', $message), ['slot' => 'morning', 'body' => 'Rise in Him today.', 'reference' => 'Psalm 5:3'])->assertRedirect();
    expect($message->fresh()->only('slot', 'body', 'reference', 'is_active'))->toBe(['slot' => 'morning', 'body' => 'Rise in Him today.', 'reference' => 'Psalm 5:3', 'is_active' => false]);

    $this->actingAs($admin)->post(route('super-admin.inspiration.store'), ['slot' => 'night', 'body' => 'x'])->assertSessionHasErrors('slot');
    $this->actingAs($admin)->post(route('super-admin.inspiration.store'), ['slot' => 'morning', 'body' => str_repeat('a', 281)])->assertSessionHasErrors('body');

    $this->actingAs($admin)->delete(route('super-admin.inspiration.destroy', $message))->assertRedirect();
    expect(Inspiration::whereKey($message->id)->exists())->toBeFalse();
});
