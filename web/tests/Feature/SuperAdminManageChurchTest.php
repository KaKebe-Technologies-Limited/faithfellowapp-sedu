<?php

use App\Models\AuditLog;
use App\Models\Church;
use Laravel\Sanctum\Sanctum;

/*
| The super admin can open any church to manage it (on the website and in the app) and update things for it. The visit is
| recorded, a reminder bar says whose church it is, and nobody else gets in.
*/

test('the super admin opens any church from the church list, and the visit is recorded', function () {
    $admin = superAdmin();
    $church = Church::factory()->create(['name' => 'Gulu Grace Chapel', 'status' => 'active']);

    $this->actingAs($admin)->get(route('super-admin.churches.index'))->assertOk()->assertSee('Manage')->assertSee(route('super-admin.churches.manage', $church), false);
    $this->actingAs($admin)->get(route('super-admin.churches.show', $church))->assertOk()->assertSee('Manage this church');

    $this->actingAs($admin)->get(route('super-admin.churches.manage', $church))->assertRedirect(route('church-admin.dashboard', $church));
    expect(AuditLog::where('action', 'church.managed')->where('church_id', $church->id)->where('user_id', $admin->id)->exists())->toBeTrue();
});

test('inside the church the super admin gets a reminder bar and every tool', function () {
    $admin = superAdmin();
    $church = Church::factory()->create(['name' => 'Gulu Grace Chapel', 'status' => 'active']);

    $this->actingAs($admin)->get(route('church-admin.dashboard', $church))->assertOk()
        ->assertSee('You are managing Gulu Grace Chapel as the super admin')->assertSee('Back to Super admin');
    $this->actingAs($admin)->get(route('church-admin.announcements.index', $church))->assertOk();
    $this->actingAs($admin)->get(route('church-admin.settings.giving', $church))->assertOk();
});

test('nobody else can open a church they do not work for', function () {
    $church = Church::factory()->create(['status' => 'active']);

    $this->actingAs(member())->get(route('super-admin.churches.manage', $church))->assertForbidden();
    $this->actingAs(member())->get(route('church-admin.dashboard', $church))->assertForbidden();
});

test('in the app the super admin lists every church, finds one and opens its dashboard', function () {
    $alpha = Church::factory()->create(['name' => 'Alpha Church', 'city' => 'Lira', 'status' => 'active']);
    $beta = Church::factory()->create(['name' => 'Beta Chapel', 'city' => 'Gulu', 'status' => 'active']);
    Church::factory()->create(['name' => 'Hidden Church', 'status' => 'pending']);
    Sanctum::actingAs(superAdmin(), ['member']);

    $this->getJson(route('api.v1.me'))->assertJsonPath('data.is_super_admin', true);

    $all = $this->getJson(route('api.v1.admin.churches'))->assertOk()->json('data');
    expect(collect($all)->pluck('church.name')->all())->toBe(['Alpha Church', 'Beta Chapel'])
        ->and($all[0])->toMatchArray(['title' => 'Super admin', 'is_admin' => true])->and($all[0]['permissions'])->not->toBeEmpty();

    expect(collect($this->getJson(route('api.v1.admin.churches', ['q' => 'gulu']))->json('data'))->pluck('church.slug')->all())->toBe([$beta->slug]);

    $this->getJson(route('api.v1.admin.dashboard', $alpha))->assertOk()->assertJsonPath('data.church.name', 'Alpha Church')->assertJsonPath('data.is_admin', true);
});

test('in the app an ordinary member is not an admin and cannot open a church dashboard', function () {
    $church = Church::factory()->create(['status' => 'active']);
    Sanctum::actingAs(member(), ['member']);

    $this->getJson(route('api.v1.me'))->assertJsonPath('data.is_super_admin', false);
    $this->getJson(route('api.v1.admin.churches'))->assertOk()->assertJson(['data' => []]);
    $this->getJson(route('api.v1.admin.dashboard', $church))->assertForbidden();
});
