<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\DevotionalRequest;
use App\Models\Church;
use App\Models\Devotional;
use App\Services\ContentPublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DevotionalController extends Controller
{
    public function __construct(protected ContentPublisher $publisher) {}

    public function index(Church $church): View
    {
        return view('church-admin.devotionals.index', [
            'church' => $church,
            'devotionals' => $church->devotionals()->with('media')->latest('publish_date')->paginate(20),
        ]);
    }

    public function create(Church $church): View
    {
        return view('church-admin.devotionals.form', [
            'church' => $church,
            'devotional' => new Devotional(['publish_date' => today(), 'status' => 'published', 'visibility' => 'public']),
        ]);
    }

    public function store(DevotionalRequest $request, Church $church): RedirectResponse
    {
        $devotional = $church->devotionals()->create([...$this->attributes($request), 'user_id' => $request->user()->id]);
        $this->attachAudio($request, $devotional);
        $this->publisher->announceIfLive($devotional->load('church'));

        return redirect()->route('church-admin.devotionals.index', $church)->with('success', __('Devotional saved.'));
    }

    public function edit(Church $church, Devotional $devotional): View
    {
        return view('church-admin.devotionals.form', ['church' => $church, 'devotional' => $devotional->load('media')]);
    }

    public function update(DevotionalRequest $request, Church $church, Devotional $devotional): RedirectResponse
    {
        $devotional->update($this->attributes($request));
        $this->attachAudio($request, $devotional);
        $this->publisher->announceIfLive($devotional->load('church'));

        return redirect()->route('church-admin.devotionals.index', $church)->with('success', __('Devotional updated.'));
    }

    public function destroy(Church $church, Devotional $devotional): RedirectResponse
    {
        $devotional->delete();

        return back()->with('success', __('Devotional deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function attributes(DevotionalRequest $request): array
    {
        $data = $request->safe()->except('audio');
        $data['body'] = ff_safe_html($data['body']);

        return $data;
    }

    protected function attachAudio(DevotionalRequest $request, Devotional $devotional): void
    {
        if ($request->hasFile('audio')) {
            $devotional->addMediaFromRequest('audio')->toMediaCollection('audio');
        }
    }
}
