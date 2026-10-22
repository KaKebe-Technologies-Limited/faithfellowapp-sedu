<?php

use App\Models\AuditLog;
use App\Models\Church;
use App\Models\Sermon;
use App\Services\ContentPublisher;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Route;

/*
| An unexpected server error leaves a note in Super admin → Audit logs and gives the person a short reference; a sermon that is saved is never
| lost because telling the members failed.
*/

function errorNoteChurch(): array
{
    $admin = member();
    $church = Church::factory()->create();
    $church->staffMembers()->create(['user_id' => $admin->id, 'is_admin' => true, 'status' => 'active']);

    return [$church, $admin];
}

test('a server error is noted for the super admin and the person is given its reference', function () {
    Route::middleware('web')->get('/__boom', fn () => throw new RuntimeException('Disk is full at '.base_path('storage/app/public/9/clip.mp4')));

    $response = $this->getJson('/__boom')->assertStatus(500);
    $reference = $response->json('reference');

    expect($reference)->toMatch('/^[A-Z0-9]{6}$/');
    $note = AuditLog::where('action', 'error.server')->first();
    expect($note)->not->toBeNull()
        ->and($note->description)->toContain("[{$reference}]")->toContain('RuntimeException: Disk is full')
        ->and($note->description)->not->toContain(base_path())
        ->and($note->new_values['request'])->toBe('GET /__boom')
        ->and($note->new_values['class'])->toBe(RuntimeException::class);

    $this->actingAs(superAdmin())->get(route('super-admin.audit-logs'))->assertOk()->assertSee('error.server')->assertSee("[{$reference}]", false);
});

test('a missing page, a validation problem or a sign-in is not a server error and leaves no note', function () {
    $this->getJson('/__not-a-page')->assertNotFound();
    $this->postJson(route('api.v1.auth.token'), [])->assertUnprocessable();
    $this->getJson(route('api.v1.me'))->assertUnauthorized();

    expect(AuditLog::where('action', 'error.server')->count())->toBe(0);
});

test('the sermon is saved even when telling the members fails, and the scheduler tells them later', function () {
    [$church, $admin] = errorNoteChurch();
    $calls = 0;
    $this->mock(NotificationService::class, function ($mock) use (&$calls) {
        $mock->shouldReceive('notifyChurch')->andReturnUsing(function () use (&$calls) {
            if (++$calls === 1) {
                throw new RuntimeException('The queue is not reachable');
            }
        });
    });

    $this->actingAs($admin)->postJson(route('church-admin.sermons.store', $church), ['title' => 'Walking by faith', 'language' => 'en', 'status' => 'published', 'visibility' => 'public'])
        ->assertOk()->assertJsonStructure(['redirect', 'message']);

    $sermon = Sermon::where('title', 'Walking by faith')->first();
    expect($sermon)->not->toBeNull()->and($sermon->notified_at)->toBeNull()
        ->and(AuditLog::where('action', 'error.server')->where('description', 'like', '%The queue is not reachable%')->exists())->toBeTrue();

    // The next minute the scheduler finds it, and this time the members are told, once.
    expect(app(ContentPublisher::class)->publishDue())->toBe(1)->and($sermon->fresh()->notified_at)->not->toBeNull()->and($calls)->toBe(2);
    expect(app(ContentPublisher::class)->publishDue())->toBe(0);
});
