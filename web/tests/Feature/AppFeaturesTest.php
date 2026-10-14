<?php

use App\Models\AuditLog;
use App\Models\Church;
use App\Models\User;
use App\Support\AppFeatures;
use Laravel\Sanctum\Sanctum;

/*
| Podcasts, the hymn book and the gospel radio belong to the whole platform. The super admin switches them on or off church by
| church; a new sign-up with no church sees all three; someone with churches sees a feature when one of them has it on.
*/

function featureChurch(array $switches = []): Church
{
    $church = Church::factory()->create();
    $church->forceFill(array_merge(['show_podcasts' => true, 'show_hymns' => true, 'show_radio' => true], $switches))->save();

    return $church;
}

function featureMember(?Church ...$churches): User
{
    $user = member();
    foreach (array_filter($churches) as $church) {
        $church->subscriptions()->create(['user_id' => $user->id, 'status' => 'active', 'stage' => 'member', 'subscribed_at' => now()]);
    }

    return $user;
}

test('a new sign-up with no church sees podcasts, hymns and radio, and can use them', function () {
    $user = featureMember();
    Sanctum::actingAs($user, ['member']);

    $this->getJson(route('api.v1.me'))->assertOk()->assertJsonPath('data.features', ['podcasts' => true, 'hymns' => true, 'radio' => true]);
    $this->getJson(route('api.v1.hymns.hymnals'))->assertOk();
    $this->getJson(route('api.v1.podcasts.index'))->assertOk();
    $this->getJson(route('api.v1.radio'))->assertOk();
});

test('every church sees all three until the super admin switches one off', function () {
    $church = featureChurch();
    expect(AppFeatures::for(featureMember($church)))->toBe(['podcasts' => true, 'hymns' => true, 'radio' => true]);
    expect(Church::first()->show_podcasts)->toBeTrue();
});

test('members of a church that has a feature off do not see it, and the server refuses them', function () {
    $church = featureChurch(['show_podcasts' => false, 'show_radio' => false]);
    Sanctum::actingAs(featureMember($church), ['member']);

    $this->getJson(route('api.v1.me'))->assertJsonPath('data.features', ['podcasts' => false, 'hymns' => true, 'radio' => false]);
    $this->getJson(route('api.v1.hymns.hymnals'))->assertOk();
    $this->getJson(route('api.v1.podcasts.index'))->assertForbidden()->assertJson(['feature' => 'podcasts']);
    $this->getJson(route('api.v1.podcasts.overview'))->assertForbidden();
    $this->getJson(route('api.v1.radio'))->assertForbidden()->assertJson(['feature' => 'radio']);
});

test('someone who follows several churches sees a feature when at least one of them has it on', function () {
    $off = featureChurch(['show_hymns' => false]);
    $on = featureChurch();
    $user = featureMember($off, $on);

    expect(AppFeatures::for($user)['hymns'])->toBeTrue();
    expect(AppFeatures::for(featureMember($off))['hymns'])->toBeFalse();
});

test('church staff are judged by the church they work for, and the super admin sees everything', function () {
    $off = featureChurch(['show_podcasts' => false]);
    $on = featureChurch();
    $user = featureMember($off);
    $user->staffMemberships()->create(['church_id' => $on->id, 'status' => 'active', 'role' => 'admin']);

    expect(AppFeatures::for($user)['podcasts'])->toBeTrue();
    expect(AppFeatures::for(superAdmin()))->toBe(['podcasts' => true, 'hymns' => true, 'radio' => true]);
    expect(AppFeatures::for(null))->toBe(['podcasts' => true, 'hymns' => true, 'radio' => true]);
});

test('a church that is not active does not count against its members', function () {
    $suspended = featureChurch(['show_podcasts' => false, 'show_hymns' => false, 'show_radio' => false]);
    $suspended->forceFill(['status' => 'suspended'])->save();

    expect(AppFeatures::for(featureMember($suspended)))->toBe(['podcasts' => true, 'hymns' => true, 'radio' => true]);
});

// ── The super admin page ────────────────────────────────────────────────────────────────────────────────

test('only the super admin can open or change the app features', function () {
    $this->actingAs(member())->get(route('super-admin.features.index'))->assertForbidden();
    $this->actingAs(member())->post(route('super-admin.features.all'), ['feature' => 'radio', 'value' => 0])->assertForbidden();
    $this->get(route('super-admin.features.index'))->assertForbidden();
});

test('the super admin sees every church and saves which ones have which feature', function () {
    $admin = superAdmin();
    $grace = featureChurch();
    $other = featureChurch();

    $this->actingAs($admin)->get(route('super-admin.features.index'))->assertOk()->assertSee($grace->name)->assertSee('Gospel radio');

    // Grace keeps podcasts and hymns but loses radio; the other church keeps only the radio.
    $this->actingAs($admin)->put(route('super-admin.features.update'), [
        'ids' => [$grace->id, $other->id],
        'on' => ['podcasts' => [$grace->id], 'hymns' => [$grace->id], 'radio' => [$other->id]],
    ])->assertRedirect();

    expect($grace->fresh()->only('show_podcasts', 'show_hymns', 'show_radio'))->toBe(['show_podcasts' => true, 'show_hymns' => true, 'show_radio' => false])
        ->and($other->fresh()->only('show_podcasts', 'show_hymns', 'show_radio'))->toBe(['show_podcasts' => false, 'show_hymns' => false, 'show_radio' => true]);
    expect(AuditLog::where('action', 'church.features')->count())->toBe(2);

    // Saving the same choices again changes nothing and writes nothing.
    $this->actingAs($admin)->put(route('super-admin.features.update'), ['ids' => [$grace->id, $other->id], 'on' => ['podcasts' => [$grace->id], 'hymns' => [$grace->id], 'radio' => [$other->id]]])->assertRedirect();
    expect(AuditLog::where('action', 'church.features')->count())->toBe(2);
});

test('one feature can be switched on or off for every church at once', function () {
    $admin = superAdmin();
    $a = featureChurch();
    $b = featureChurch();

    $this->actingAs($admin)->post(route('super-admin.features.all'), ['feature' => 'podcasts', 'value' => 0])->assertRedirect();
    expect($a->fresh()->show_podcasts)->toBeFalse()->and($b->fresh()->show_podcasts)->toBeFalse()->and($a->fresh()->show_hymns)->toBeTrue();

    $this->actingAs($admin)->post(route('super-admin.features.all'), ['feature' => 'podcasts', 'value' => 1])->assertRedirect();
    expect($b->fresh()->show_podcasts)->toBeTrue();
    $this->actingAs($admin)->post(route('super-admin.features.all'), ['feature' => 'nonsense', 'value' => 1])->assertSessionHasErrors('feature');
});
