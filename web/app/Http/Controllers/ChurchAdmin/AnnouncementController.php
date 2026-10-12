<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\Church;
use App\Services\ContentPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(protected ContentPublisher $publisher) {}

    public function index(Church $church): View
    {
        return view('church-admin.announcements.index', [
            'church' => $church,
            'announcements' => $church->announcements()->with(['branch', 'media'])->latest('published_at')->latest('id')->paginate(20),
        ]);
    }

    public function create(Church $church): View
    {
        return $this->form($church, new Announcement(['audience' => 'members', 'priority' => 'normal', 'status' => 'published']));
    }

    public function store(AnnouncementRequest $request, Church $church): RedirectResponse
    {
        $announcement = $church->announcements()->create([...$this->attributes($request), 'user_id' => $request->user()->id]);
        $this->afterSave($request, $announcement);

        return redirect()->route('church-admin.announcements.index', $church)->with('success', $announcement->status === 'scheduled'
            ? __('Announcement scheduled.') : __('Announcement saved.'));
    }

    public function edit(Church $church, Announcement $announcement): View
    {
        return $this->form($church, $announcement->load('media'));
    }

    public function update(AnnouncementRequest $request, Church $church, Announcement $announcement): RedirectResponse
    {
        $announcement->update($this->attributes($request));
        $this->afterSave($request, $announcement);

        return redirect()->route('church-admin.announcements.index', $church)->with('success', __('Announcement updated.'));
    }

    public function destroy(Church $church, Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', __('Announcement deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(AnnouncementRequest $request): array
    {
        $data = Announcement::resolvePublishing($request->safe()->except('image'));
        $data['body'] = ff_safe_html($data['body']);
        $data['channels'] = $data['channels'] ?? [];

        return $data;
    }

    protected function afterSave(AnnouncementRequest $request, Announcement $announcement): void
    {
        if ($request->hasFile('image')) {
            $announcement->addMediaFromRequest('image')->toMediaCollection('image');
        }
        $this->publisher->announceIfLive($announcement->load('church'));
    }

    protected function form(Church $church, Announcement $announcement): View
    {
        return view('church-admin.announcements.form', [
            'church' => $church,
            'announcement' => $announcement,
            'branches' => $church->branches()->orderBy('name')->pluck('name', 'id'),
            'groups' => Schema::hasTable('small_groups')
                ? \DB::table('small_groups')->where('church_id', $church->id)->whereNull('deleted_at')->orderBy('name')->pluck('name', 'id')
                : collect(),
        ]);
    }
}
