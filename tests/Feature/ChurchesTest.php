<?php

use App\Models\AuditLog;
use App\Models\Church;
use App\Models\ChurchRole;
use App\Models\User;
use App\Notifications\Church\ChurchApplicationReceived;
use App\Notifications\Church\ChurchStatusChanged;
use App\Notifications\Church\StaffInvitation;
use App\Services\ChurchOnboardingService;
use Illuminate\Support\Facades\Notification;

/** Create an approved church with an admin user. */
function churchWithAdmin(array $attributes = []): array
{
    $admin = member();
    $church = Church::factory()->pending()->create($attributes + ['owner_id' => $admin->id]);
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);
    app(ChurchOnboardingService::class)->approve($church, notify: false);

    return [$church->fresh(), $admin];
}

test('directory lists only active churches', function () {
    Church::factory()->create(['name' => 'Active Grace Church', 'city' => 'Lira']);
    Church::factory()->pending()->create(['name' => 'Pending Hope Church']);

    $this->get('/churches')->assertOk()->assertSee('Active Grace Church')->assertDontSee('Pending Hope Church');
    $this->get('/churches?city=Gulu')->assertDontSee('Active Grace Church');
});

test('pending churches are hidden from the public but visible to staff', function () {
    $admin = member();
    $church = Church::factory()->pending()->create();
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);

    $this->get('/churches/'.$church->slug)->assertForbidden();
    $this->actingAs($admin)->get('/churches/'.$church->slug)->assertOk();
});

test('a member can apply to register a church', function () {
    Notification::fake();
    $super = superAdmin();
    $user = member();

    $this->actingAs($user)->post('/register-church', [
        'name' => 'Hope Chapel Arua', 'city' => 'Arua', 'phone' => '0772123456', 'languages' => ['en', 'luo'], 'agree' => '1',
    ])->assertRedirect();

    $church = Church::where('name', 'Hope Chapel Arua')->first();
    expect($church->status)->toBe('pending')
        ->and($church->slug)->toBe('hope-chapel-arua')
        ->and($user->fresh()->isChurchAdmin($church))->toBeTrue()
        ->and($user->fresh()->hasRole('church_admin'))->toBeTrue();

    Notification::assertSentTo($super, ChurchApplicationReceived::class);
});

test('super admin approval activates the church with preset roles and a main branch', function () {
    Notification::fake();
    $owner = member();
    $church = Church::factory()->pending()->create(['owner_id' => $owner->id]);

    $this->actingAs(superAdmin())->post(route('super-admin.churches.approve', $church))->assertRedirect();

    $church->refresh();
    expect($church->status)->toBe('active')
        ->and($church->roles()->pluck('name')->sort()->values()->all())->toBe(['Editor', 'Events', 'Finance', 'Media', 'Moderator'])
        ->and($church->branches()->where('is_main', true)->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'church.approved')->where('church_id', $church->id)->exists())->toBeTrue();

    Notification::assertSentTo($owner, ChurchStatusChanged::class, fn ($n) => $n->change === 'approved');
});

test('only super admins can approve or suspend churches', function () {
    [$church, $admin] = churchWithAdmin();

    $this->actingAs($admin)->post(route('super-admin.churches.suspend', $church))->assertForbidden();
    expect($church->fresh()->status)->toBe('active');
});

test('super admin can onboard a church and invite its admin', function () {
    Notification::fake();

    $this->actingAs(superAdmin())->post(route('super-admin.churches.store'), [
        'name' => 'Victory Chapel', 'city' => 'Mbarara', 'admin_name' => 'Pastor John', 'admin_email' => 'john@victory.test', 'activate' => '1',
    ])->assertRedirect();

    $church = Church::where('name', 'Victory Chapel')->firstOrFail();
    $admin = User::where('email', 'john@victory.test')->firstOrFail();
    expect($church->status)->toBe('active')->and($admin->isChurchAdmin($church))->toBeTrue();
    Notification::assertSentTo($admin, StaffInvitation::class, fn ($n) => $n->passwordToken !== null);
});

test('church staff cannot access another church admin area', function () {
    [$churchA, $adminA] = churchWithAdmin();
    [$churchB] = churchWithAdmin();

    $this->actingAs($adminA)->get(route('church-admin.dashboard', $churchA))->assertOk();
    $this->actingAs($adminA)->get(route('church-admin.dashboard', $churchB))->assertForbidden();
    $this->actingAs(member())->get(route('church-admin.dashboard', $churchA))->assertForbidden();
});

test('scoped bindings prevent loading another church’s records', function () {
    [$churchA, $adminA] = churchWithAdmin();
    [$churchB] = churchWithAdmin();
    $branchB = $churchB->branches()->first();

    $this->actingAs($adminA)->get("/church/{$churchA->slug}/admin/branches/{$branchB->id}/edit")->assertNotFound();
});

test('sub-role permissions are enforced server side', function () {
    [$church] = churchWithAdmin();
    $finance = member();
    $church->staffMembers()->create(['user_id' => $finance->id, 'church_role_id' => $church->roles()->where('slug', 'finance')->value('id'), 'status' => 'active']);

    expect($finance->canInChurch($church, 'giving.view'))->toBeTrue()
        ->and($finance->canInChurch($church, 'content.manage'))->toBeFalse();

    $this->actingAs($finance)->get(route('church-admin.dashboard', $church))->assertOk();
    $this->actingAs($finance)->get(route('church-admin.roles.index', $church))->assertForbidden();
    $this->actingAs($finance)->get(route('church-admin.settings.profile', $church))->assertForbidden();
});

test('admins can create custom roles from the permission catalog only', function () {
    [$church, $admin] = churchWithAdmin();

    $this->actingAs($admin)->post(route('church-admin.roles.store', $church), [
        'name' => 'Ushering Lead', 'permissions' => ['events.manage', 'members.manage'],
    ])->assertRedirect();

    $role = ChurchRole::where('church_id', $church->id)->where('name', 'Ushering Lead')->first();
    expect($role->permissions)->toBe(['events.manage', 'members.manage']);

    $this->actingAs($admin)->post(route('church-admin.roles.store', $church), [
        'name' => 'Hacker', 'permissions' => ['platform.everything'],
    ])->assertSessionHasErrors('permissions.0');
});

test('admins can invite staff and cannot remove the last admin', function () {
    Notification::fake();
    [$church, $admin] = churchWithAdmin();
    $editorRole = $church->roles()->where('slug', 'editor')->first();

    $this->actingAs($admin)->post(route('church-admin.staff.store', $church), [
        'name' => 'Media Volunteer', 'email' => 'media@church.test', 'access' => 'role', 'church_role_id' => $editorRole->id,
    ])->assertRedirect();

    $newUser = User::where('email', 'media@church.test')->firstOrFail();
    expect($newUser->canInChurch($church, 'content.manage'))->toBeTrue()
        ->and($newUser->canInChurch($church, 'giving.view'))->toBeFalse();
    Notification::assertSentTo($newUser, StaffInvitation::class);

    $adminStaff = $church->staffMembers()->where('user_id', $admin->id)->first();
    $this->actingAs($admin)->delete(route('church-admin.staff.destroy', [$church, $adminStaff]))->assertStatus(422);
});

test('roles from another church cannot be assigned', function () {
    [$churchA, $adminA] = churchWithAdmin();
    [$churchB] = churchWithAdmin();

    $this->actingAs($adminA)->post(route('church-admin.staff.store', $churchA), [
        'name' => 'X', 'email' => 'x@test.test', 'access' => 'role', 'church_role_id' => $churchB->roles()->first()->id,
    ])->assertSessionHasErrors('church_role_id');
});

test('setup wizard saves service times', function () {
    [$church, $admin] = churchWithAdmin();

    $this->actingAs($admin)->post(route('church-admin.setup.update', $church), [
        'step' => 'services',
        'services' => [
            ['name' => 'Sunday Service', 'day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '11:00', 'language' => 'en'],
            ['name' => 'Bible Study', 'day_of_week' => 3, 'start_time' => '18:00', 'language' => 'luo'],
        ],
    ])->assertRedirect();

    expect($church->serviceTimes()->count())->toBe(2);
    $this->get('/churches?day=3')->assertSee($church->name);
});

test('website builder saves block order and visibility', function () {
    [$church, $admin] = churchWithAdmin();
    $blocks = [['type' => 'about', 'enabled' => true], ['type' => 'hero', 'enabled' => false], ['type' => 'contact', 'enabled' => true]];

    $this->actingAs($admin)->put(route('church-admin.settings.website.update', $church), [
        'blocks' => json_encode($blocks), 'hero_title' => 'Welcome home',
    ])->assertRedirect();

    expect($church->fresh()->website_blocks)->toBe($blocks)
        ->and($church->fresh()->setting('hero_title'))->toBe('Welcome home');
});

test('church websites are served on subdomains', function () {
    $church = Church::factory()->create(['name' => 'Subdomain Church', 'slug' => 'subdomain-church']);

    $this->get('http://subdomain-church.'.config('faithfellows.root_domain').'/')->assertOk()->assertSee('Subdomain Church');
    $this->get('http://unknown-church.'.config('faithfellows.root_domain').'/')->assertNotFound();
});

test('verified custom domains render the church website', function () {
    Church::factory()->create(['name' => 'Custom Domain Church', 'custom_domain' => 'gracechurch.org', 'custom_domain_verified' => true]);

    $this->get('http://www.gracechurch.org/')->assertOk()->assertSee('Custom Domain Church');
});

test('super admins can impersonate church admins and it is audited', function () {
    [$church, $admin] = churchWithAdmin();
    $super = superAdmin();

    $this->actingAs($super)->post(route('super-admin.impersonate.start', $admin))->assertRedirect();
    $this->assertAuthenticatedAs($admin);
    expect(AuditLog::where('action', 'impersonation.started')->exists())->toBeTrue();

    $this->post(route('impersonate.stop'))->assertRedirect(route('super-admin.dashboard'));
    $this->assertAuthenticatedAs($super);
});

test('church slugs are unique and avoid reserved words', function () {
    Church::factory()->create(['slug' => 'grace']);

    expect(Church::uniqueSlug('Grace'))->toBe('grace-2')
        ->and(Church::uniqueSlug('Admin'))->toBe('admin-church');
});
