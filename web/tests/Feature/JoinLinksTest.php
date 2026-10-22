<?php

use App\Models\Church;
use App\Models\JoinLinkVisit;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Services\JoinLinks;
use Laravel\Sanctum\Sanctum;

/*
| Every church has a link of its own (https://faithfellows.com/join/CODE). Opening it shows the church and either opens the app on it or sends the
| person to download the app; the app follows the church once the person has an account.
*/

const INVITE_ANDROID = 'Mozilla/5.0 (Linux; Android 14; SM-A146B) AppleWebKit/537.36 Chrome/126.0 Mobile Safari/537.36';
const INVITE_IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 Version/17.5 Mobile/15E148 Safari/604.1';

const INVITE_DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36';

/** Opens the link as a phone (or computer) at an address would. Headers and addresses stick to a test's later requests, so each call sets both. */
function openInviteLink(Church $church, string $agent = INVITE_DESKTOP, string $ip = '127.0.0.1')
{
    return test()->flushHeaders()->withServerVariables(['REMOTE_ADDR' => $ip])->withHeaders(['User-Agent' => $agent])->get(route('join.show', $church->join_code));
}

/** The app, opened for the first time, asking which church this phone came from. */
function matchInviteFrom(string $platform, string $ip = '127.0.0.1')
{
    return test()->flushHeaders()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson(route('api.v1.join.match'), ['platform' => $platform]);
}

function inviteChurch(array $attributes = []): Church
{
    return Church::factory()->create(['name' => 'Grace Community Church Lira', 'city' => 'Lira', ...$attributes]);
}

function inviteChurchWithAdmin(array $attributes = []): array
{
    $admin = member();
    $church = inviteChurch($attributes);
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);

    return [$church, $admin];
}

test('a new church gets its own code from its name, and no two churches share one', function () {
    $a = inviteChurch();
    $b = inviteChurch();

    expect($a->join_code)->toMatch('/^GRACE[A-Z0-9]{3}$/')
        ->and($b->join_code)->toMatch('/^GRACE[A-Z0-9]{3}$/')
        ->and($a->join_code)->not->toBe($b->join_code)
        ->and(app(JoinLinks::class)->url($a))->toBe(route('join.show', $a->join_code));
});

test('a code is found from a link, the app address or whatever the person typed', function () {
    expect(JoinLinks::normalise('https://faithfellows.com/join/grace7kq?utm=x'))->toBe('GRACE7KQ')
        ->and(JoinLinks::normalise('faithfellows://join/GRACE7KQ'))->toBe('GRACE7KQ')
        ->and(JoinLinks::normalise(' grace 7kq '))->toBe('GRACE7KQ')
        ->and(JoinLinks::normalise('grace-7kq'))->toBe('GRACE7KQ');

    $church = inviteChurch();
    $links = app(JoinLinks::class);
    expect($links->find(strtolower($church->join_code))?->id)->toBe($church->id)
        ->and($links->find("https://faithfellows.com/join/{$church->join_code}")?->id)->toBe($church->id)
        ->and($links->find('NOSUCH'))->toBeNull()
        ->and($links->find(''))->toBeNull();
});

test('the link page shows the church, the code and a button that opens the app', function () {
    $church = inviteChurch(['tagline' => 'A family that prays']);

    openInviteLink($church, INVITE_ANDROID)->assertOk()
        ->assertSee('Grace Community Church Lira')->assertSee('A family that prays')->assertSee($church->join_code)
        ->assertSee('Open in the FaithFellows app')
        ->assertSee('intent://join/'.$church->join_code, false)->assertSee('package=tech.kakebe.faithfellows', false);

    openInviteLink($church, INVITE_IPHONE)->assertOk()->assertSee('faithfellows://join/'.$church->join_code, false);

    // On a computer it shows a QR code to scan with a phone.
    openInviteLink($church)->assertOk()->assertSee('<svg', false)->assertSee('Scan this with your phone camera');
});

test('the download buttons come from the platform settings, and the Play Store link carries the church code', function () {
    $church = inviteChurch();
    PlatformSetting::put('android_app_url', 'https://play.google.com/store/apps/details?id=tech.kakebe.faithfellows');
    PlatformSetting::put('ios_app_url', 'https://testflight.apple.com/join/ABC123');

    $android = openInviteLink($church, INVITE_ANDROID)->assertOk()->getContent();
    expect($android)->toContain('S.browser_fallback_url='.rawurlencode('https://play.google.com/store/apps/details?id=tech.kakebe.faithfellows&referrer='.rawurlencode('faithfellows_join='.$church->join_code)))
        ->and($android)->toContain('Download the app');

    openInviteLink($church, INVITE_IPHONE)->assertOk()
        ->assertSee('https://testflight.apple.com/join/ABC123')->assertSee('Get the app for iPhone');
});

test('an iPhone with no iPhone app yet is told so and can join on the website', function () {
    $church = inviteChurch();
    PlatformSetting::put('ios_app_url', '');

    openInviteLink($church, INVITE_IPHONE)->assertOk()
        ->assertSee('The iPhone app is coming soon')->assertSee(route('churches.join', $church));
});

test('a wrong code, or a church that is not active, shows a friendly page and not a church', function () {
    $this->get(route('join.show', 'NOSUCH1'))->assertNotFound()->assertSee('This church link is not working')->assertSee('NOSUCH1')->assertSee(route('churches.index'));

    $suspended = inviteChurch(['status' => 'suspended']);
    $this->get(route('join.show', $suspended->join_code))->assertNotFound()->assertDontSee('Grace Community Church Lira');
});

test('opening the link is counted for the church, and counted people are those who joined through it', function () {
    $church = inviteChurch();
    openInviteLink($church, INVITE_IPHONE);
    openInviteLink($church, INVITE_ANDROID);
    Subscription::create(['user_id' => member()->id, 'church_id' => $church->id, 'status' => 'active', 'source' => 'invite', 'subscribed_at' => now()]);
    Subscription::create(['user_id' => member()->id, 'church_id' => $church->id, 'status' => 'active', 'source' => 'directory', 'subscribed_at' => now()]);

    expect(app(JoinLinks::class)->stats($church))->toBe(['opened' => 2, 'opened_30' => 2, 'joined' => 1, 'joined_30' => 1])
        ->and(JoinLinkVisit::pluck('platform')->all())->toEqualCanonicalizing(['ios', 'android']);
});

test('the app asks which church a code is before there is an account', function () {
    $church = inviteChurch();

    $this->getJson(route('api.v1.join.show', $church->join_code))->assertOk()
        ->assertJsonPath('data.code', $church->join_code)->assertJsonPath('data.church.name', 'Grace Community Church Lira')
        ->assertJsonPath('data.church.slug', $church->slug)->assertJsonPath('data.requires_approval', false);
    $this->getJson(route('api.v1.join.show', 'grace'.substr($church->join_code, 5)))->assertOk();
    $this->getJson(route('api.v1.join.show', 'NOSUCH1'))->assertNotFound();
});

test('a member who has the link follows the church, and the church is told how they came', function () {
    $church = inviteChurch();
    $user = member();

    $this->postJson(route('api.v1.join.follow', $church->join_code))->assertUnauthorized();

    Sanctum::actingAs($user, ['member']);
    $this->postJson(route('api.v1.join.follow', $church->join_code))->assertOk()
        ->assertJsonPath('status', 'active')->assertJsonPath('message', 'You now follow Grace Community Church Lira.');

    expect(Subscription::where('user_id', $user->id)->where('church_id', $church->id)->first())->status->toBe('active')->source->toBe('invite');

    // Opening the link again changes nothing.
    $this->postJson(route('api.v1.join.follow', $church->join_code))->assertOk()->assertJsonPath('status', 'active');
    expect(Subscription::where('user_id', $user->id)->count())->toBe(1);
});

test('a church that approves new members gets a request, and a member at the limit is told why', function () {
    $strict = inviteChurch(['requires_member_approval' => true]);
    Sanctum::actingAs($user = member(), ['member']);

    $this->postJson(route('api.v1.join.follow', $strict->join_code))->assertOk()->assertJsonPath('status', 'pending')
        ->assertJsonPath('message', 'Request sent. Grace Community Church Lira approves new members.');

    foreach (range(1, 2) as $i) {
        Subscription::create(['user_id' => $user->id, 'church_id' => inviteChurch()->id, 'status' => 'active', 'source' => 'directory', 'subscribed_at' => now()]);
    }
    $this->postJson(route('api.v1.join.follow', inviteChurch()->join_code))->assertUnprocessable()->assertJsonValidationErrors('church');
});

test('a fresh install is offered the church whose link this phone opened, once', function () {
    $church = inviteChurch();

    // The link was opened on an Android phone: the app on an Android phone from the same address is offered the church, one time.
    openInviteLink($church, INVITE_ANDROID);
    matchInviteFrom('ios')->assertOk()->assertJsonPath('data', null);
    matchInviteFrom('android')->assertOk()->assertJsonPath('data.church.slug', $church->slug);
    matchInviteFrom('android')->assertOk()->assertJsonPath('data', null);

    matchInviteFrom('windows')->assertUnprocessable();
});

test('nothing is guessed when two churches were opened from one address, or from another address', function () {
    $church = inviteChurch();
    $other = inviteChurch(['name' => 'Hope Chapel']);

    openInviteLink($church, INVITE_ANDROID);
    openInviteLink($other, INVITE_ANDROID);
    matchInviteFrom('android')->assertOk()->assertJsonPath('data', null);

    // A link opened at another address belongs to the person there, not to this one.
    openInviteLink($church, INVITE_IPHONE, '10.9.8.7');
    matchInviteFrom('ios')->assertOk()->assertJsonPath('data', null);
    matchInviteFrom('ios', '10.9.8.7')->assertOk()->assertJsonPath('data.church.slug', $church->slug);
});

test('the match is only good for a day, and the fingerprints are forgotten after three', function () {
    $church = inviteChurch();
    openInviteLink($church, INVITE_IPHONE);

    $this->travel(25)->hours();
    matchInviteFrom('ios')->assertOk()->assertJsonPath('data', null);

    expect(app(JoinLinks::class)->prune())->toBe(0);
    $this->travel(3)->days();
    expect(app(JoinLinks::class)->prune())->toBe(1)->and(JoinLinkVisit::first()->fingerprint)->toBeNull()->and(JoinLinkVisit::count())->toBe(1);
});

test('church staff see the invite link, its QR code and its figures; other people do not', function () {
    [$church, $admin] = inviteChurchWithAdmin(['name' => 'Grace Community Church Lira']);

    $this->actingAs($admin)->get(route('church-admin.invite.show', $church))->assertOk()
        ->assertSee(route('join.show', $church->join_code))->assertSee($church->join_code)->assertSee('<svg', false)
        ->assertSee('Link opened')->assertSee('Joined by link');
    $this->actingAs($admin)->get(route('church-admin.invite.poster', $church))->assertOk()->assertSee('Join Grace Community Church Lira')->assertSee($church->join_code);

    $this->actingAs(member())->get(route('church-admin.invite.show', $church))->assertForbidden();
});

test('a church can choose its own code, never one that is taken, and a new code retires the old link', function () {
    [$church, $admin] = inviteChurchWithAdmin(['name' => 'Grace Community Church Lira']);
    $taken = inviteChurch(['name' => 'Hope Chapel']);
    $old = $church->join_code;

    $this->actingAs($admin)->put(route('church-admin.invite.update', $church), ['join_code' => 'abc'])->assertSessionHasErrors('join_code');
    $this->actingAs($admin)->put(route('church-admin.invite.update', $church), ['join_code' => $taken->join_code])->assertSessionHasErrors('join_code');
    $this->actingAs($admin)->put(route('church-admin.invite.update', $church), ['join_code' => 'Grace Lira'])->assertSessionHasNoErrors();

    expect($church->fresh()->join_code)->toBe('GRACELIRA');
    $this->get(route('join.show', 'GRACELIRA'))->assertOk()->assertSee('Grace Community Church Lira');
    $this->get(route('join.show', $old))->assertNotFound();

    $this->actingAs($admin)->post(route('church-admin.invite.regenerate', $church))->assertRedirect();
    expect($church->fresh()->join_code)->not->toBe('GRACELIRA');
    $this->get(route('join.show', 'GRACELIRA'))->assertNotFound();
});

test('a staff member without the settings permission can share the link but not change the code', function () {
    [$church] = inviteChurchWithAdmin();
    $role = $church->roles()->create(['name' => 'Media', 'slug' => 'media-helper', 'permissions' => ['content.manage']]);
    $staff = member();
    $church->staffMembers()->create(['user_id' => $staff->id, 'is_admin' => false, 'church_role_id' => $role->id, 'status' => 'active']);

    $this->actingAs($staff)->get(route('church-admin.invite.show', $church))->assertOk()->assertDontSee('Change code');
    $this->actingAs($staff)->put(route('church-admin.invite.update', $church), ['join_code' => 'NEWCODE1'])->assertForbidden();
});

test('staff can read the link from the app', function () {
    [$church, $admin] = inviteChurchWithAdmin(['name' => 'Grace Community Church Lira']);

    Sanctum::actingAs($admin, ['member']);
    $this->getJson(route('api.v1.admin.invite.show', $church))->assertOk()
        ->assertJsonPath('data.code', $church->join_code)->assertJsonPath('data.url', route('join.show', $church->join_code))
        ->assertJsonPath('data.stats.joined', 0)->assertJsonPath('data.ready', true);

    Sanctum::actingAs(member(), ['member']);
    $this->getJson(route('api.v1.admin.invite.show', $church))->assertForbidden();
});

test('the super admin can set the iPhone download link', function () {
    $this->actingAs(superAdmin())->put(route('super-admin.settings.update'), ['ios_app_url' => 'https://testflight.apple.com/join/XYZ789'])->assertSessionHasNoErrors();

    expect(ff_ios_app_url())->toBe('https://testflight.apple.com/join/XYZ789');
});
