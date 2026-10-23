<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AppPhoto;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The pictures of the app itself, from the system end: the opening photos, the welcome screen's photos and the default photos of the Home banner slider.
 * Upload, caption, switch off, reorder and remove; where a placement has none the app keeps the photos built into it.
 */
class AppMediaController extends Controller
{
    public function index(): View
    {
        $ready = AppPhoto::ready();

        return view('super-admin.app-media.index', [
            'ready' => $ready,
            'placements' => AppPhoto::PLACEMENTS,
            'photos' => $ready ? AppPhoto::with('media')->inOrder()->get()->groupBy('placement') : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(AppPhoto::ready(), 503, __('Apply the database update first (Super admin → Database updates).'));
        $data = $request->validate([
            'placement' => ['required', Rule::in(array_keys(AppPhoto::PLACEMENTS))],
            'photos' => ['required', 'array', 'min:1', 'max:12'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);
        $placement = $data['placement'];
        $room = AppPhoto::PLACEMENTS[$placement]['max'] - AppPhoto::ofPlacement($placement)->count();
        if ($room < count($data['photos'])) {
            return back()->withErrors(['photos' => $room > 0 ? __('There is room for :n more here. Remove one first, or choose fewer pictures.', ['n' => $room]) : __('This place already has the most pictures it can show. Remove one first.')]);
        }

        $position = (int) AppPhoto::ofPlacement($placement)->max('position');
        foreach ($data['photos'] as $file) {
            $photo = AppPhoto::create(['placement' => $placement, 'position' => ++$position]);
            $photo->addMedia($file)->usingFileName(uniqid('app-', true).'.'.($file->guessExtension() ?: 'jpg'))->toMediaCollection('photo');
        }
        Audit::log('app_media.added', null, __(':n added to :where', ['n' => count($data['photos']), 'where' => __(AppPhoto::PLACEMENTS[$placement]['label'])]));

        return back()->with('success', trans_choice(':n picture was added.|:n pictures were added.', count($data['photos']), ['n' => count($data['photos'])]));
    }

    public function update(Request $request, AppPhoto $photo): RedirectResponse
    {
        $data = $request->validate(['title' => ['nullable', 'string', 'max:80'], 'subtitle' => ['nullable', 'string', 'max:160'], 'is_active' => ['boolean']]);
        $photo->update(['title' => $data['title'] ?? null, 'subtitle' => $data['subtitle'] ?? null, 'is_active' => $request->boolean('is_active')]);

        return back()->with('success', __('Saved.'));
    }

    public function move(Request $request, AppPhoto $photo): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        $ids = AppPhoto::ofPlacement($photo->placement)->inOrder()->pluck('id')->all();
        $i = array_search($photo->id, $ids, true);
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if ($i !== false && isset($ids[$j])) {
            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            foreach (array_values($ids) as $position => $id) {
                AppPhoto::whereKey($id)->update(['position' => $position + 1]);
            }
        }

        return back();
    }

    public function destroy(AppPhoto $photo): RedirectResponse
    {
        $photo->clearMediaCollection('photo');
        $photo->delete();
        Audit::log('app_media.removed', null, __('A picture was removed from :where', ['where' => __(AppPhoto::PLACEMENTS[$photo->placement]['label'] ?? $photo->placement)]));

        return back()->with('success', __('The picture was removed.'));
    }
}
