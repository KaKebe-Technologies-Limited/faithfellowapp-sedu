<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Home-slider photos. Members see a church in their home slider once it has
 * at least four; slides from all their churches are interleaved.
 */
class SlideController extends Controller
{
    public function store(Request $request, Church $church): RedirectResponse
    {
        $max = (int) config('faithfellows.limits.max_slides', 8);
        $remaining = $max - $church->slides()->count();

        $data = $request->validate([
            'slides' => ['required', 'array', 'min:1', 'max:'.max(1, $remaining)],
            'slides.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:6144', 'dimensions:min_width=1000,min_height=400'],
            'title' => ['nullable', 'string', 'max:80'],
            'subtitle' => ['nullable', 'string', 'max:140'],
        ], ['slides.max' => __('You can have up to :max slider photos.', ['max' => $max])]);

        abort_if($remaining <= 0, 422, __('You already have :max slider photos. Remove one first.', ['max' => $max]));

        foreach ($request->file('slides') as $file) {
            $church->addMedia($file)
                ->withCustomProperties(array_filter(['title' => $data['title'] ?? null, 'subtitle' => $data['subtitle'] ?? null]))
                ->toMediaCollection('slides');
        }

        $count = $church->fresh()->slides()->count();
        $min = (int) config('faithfellows.limits.min_slides', 4);

        return back()->with('success', $count >= $min
            ? __('Slider updated — your church now appears in members’ home slider.')
            : __('Photo added. Add :n more to appear in members’ home slider.', ['n' => $min - $count]));
    }

    public function update(Request $request, Church $church, int $mediaId): RedirectResponse
    {
        $slide = $this->slide($church, $mediaId);
        $data = $request->validate(['title' => ['nullable', 'string', 'max:80'], 'subtitle' => ['nullable', 'string', 'max:140']]);
        $slide->setCustomProperty('title', $data['title'] ?? null)->setCustomProperty('subtitle', $data['subtitle'] ?? null)->save();

        return back()->with('success', __('Caption saved.'));
    }

    public function move(Request $request, Church $church, int $mediaId): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];
        $ids = $church->slides()->pluck('id')->values()->all();
        $i = array_search($mediaId, $ids, true);
        abort_if($i === false, 404);

        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if (isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            Media::setNewOrder($ids);
        }

        return back();
    }

    public function destroy(Church $church, int $mediaId): RedirectResponse
    {
        $this->slide($church, $mediaId)->delete();

        return back()->with('success', __('Photo removed from the slider.'));
    }

    protected function slide(Church $church, int $mediaId): Media
    {
        return $church->media()->where('collection_name', 'slides')->findOrFail($mediaId);
    }
}
