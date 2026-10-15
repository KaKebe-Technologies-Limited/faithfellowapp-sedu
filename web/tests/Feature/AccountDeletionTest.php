<?php

use App\Models\Church;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('a member can delete their account from the app by typing their email', function () {
    $user = member(['name' => 'Real Name', 'phone' => '0772999999', 'email' => 'real@example.com', 'google_id' => 'g-123']);
    $token = $user->createToken('phone', ['member'])->plainTextToken;
    DB::table('notes')->insert(['user_id' => $user->id, 'context' => 'bible', 'title' => 'x', 'body' => 'My private note', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('device_tokens')->insert(['user_id' => $user->id, 'token' => 'ExponentPushToken[abc]', 'platform' => 'android', 'created_at' => now(), 'updated_at' => now()]);

    $this->withToken($token)->deleteJson(route('api.v1.me.destroy'), ['email' => 'REAL@example.com'])->assertOk();

    $gone = User::withTrashed()->find($user->id);
    expect($gone->trashed())->toBeTrue()
        ->and($gone->name)->toBe('Deleted user')
        ->and($gone->email)->not->toContain('real@example.com')
        ->and($gone->phone)->toBeNull()
        ->and($gone->google_id)->toBeNull()
        ->and($gone->tokens()->count())->toBe(0)
        ->and(DB::table('notes')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('device_tokens')->where('user_id', $user->id)->count())->toBe(0);
});

test('the app asks for the right email before deleting', function () {
    $user = member(['email' => 'real@example.com']);

    $this->actingAs($user, 'sanctum')->deleteJson(route('api.v1.me.destroy'), ['email' => 'someone@else.com'])
        ->assertStatus(422)->assertJsonValidationErrors('email');
    $this->actingAs($user, 'sanctum')->deleteJson(route('api.v1.me.destroy'), [])->assertStatus(422);

    expect(User::find($user->id))->not->toBeNull();
});

test('the owner of a church cannot delete the account until the church is handed over', function () {
    $owner = member(['email' => 'owner@example.com']);
    Church::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner, 'sanctum')->deleteJson(route('api.v1.me.destroy'), ['email' => 'owner@example.com'])
        ->assertForbidden();

    expect(User::find($owner->id))->not->toBeNull();
});

test('a person who is signed out cannot delete anything', function () {
    $this->deleteJson(route('api.v1.me.destroy'), ['email' => 'x@example.com'])->assertUnauthorized();
});

test('gifts that repeat are cancelled and the records of gifts stay', function () {
    $user = member(['email' => 'giver@example.com']);
    $church = Church::factory()->create();
    DB::table('recurring_gifts')->insert([
        'user_id' => $user->id, 'church_id' => $church->id, 'amount' => 5000, 'currency' => 'UGX', 'frequency' => 'monthly', 'method' => 'mtn_momo',
        'phone' => '0772000111', 'status' => 'active', 'next_run_on' => now()->addMonth(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($user, 'sanctum')->deleteJson(route('api.v1.me.destroy'), ['email' => 'giver@example.com'])->assertOk();

    expect(DB::table('recurring_gifts')->where('user_id', $user->id)->value('status'))->toBe('cancelled');
});

test('the public page that explains account deletion is reachable and linked from the footer and the privacy policy', function () {
    $this->get(route('account.delete'))->assertOk()
        ->assertSee('Delete your FaithFellows account')
        ->assertSee('Delete my account');
    $this->get('/')->assertSee(route('account.delete'), false);
    $this->get(route('privacy'))->assertSee(route('account.delete'), false);
});
