<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ReadingPlanRequest;
use App\Models\Church;
use App\Models\ReadingPlan;
use App\Support\ReadingPlanTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    public function index(Church $church): View
    {
        return view('church-admin.reading-plans.index', [
            'church' => $church,
            'plans' => $church->readingPlans()->withCount('progress')->latest()->paginate(20),
            'templates' => ReadingPlanTemplates::all(),
        ]);
    }

    public function create(Church $church): View
    {
        return view('church-admin.reading-plans.form', ['church' => $church, 'plan' => new ReadingPlan(['is_published' => true, 'entries' => []])]);
    }

    public function store(ReadingPlanRequest $request, Church $church): RedirectResponse
    {
        $entries = $request->entries();
        $church->readingPlans()->create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'is_published' => $request->boolean('is_published', true),
            'entries' => $entries,
            'days_count' => count($entries),
        ]);

        return redirect()->route('church-admin.reading-plans.index', $church)->with('success', __('Reading plan created.'));
    }

    /**
     * Copy a built-in template (e.g. "Bible in a Year") into this church.
     */
    public function import(Request $request, Church $church): RedirectResponse
    {
        $request->validate(['template' => ['required', 'in:'.implode(',', array_keys(ReadingPlanTemplates::all()))]]);
        $template = ReadingPlanTemplates::all()[$request->input('template')];

        $church->readingPlans()->create([
            'title' => $template['title'],
            'description' => $template['description'],
            'entries' => $template['entries'],
            'days_count' => count($template['entries']),
            'is_published' => true,
        ]);

        return back()->with('success', __(':plan added to your church.', ['plan' => $template['title']]));
    }

    public function edit(Church $church, ReadingPlan $readingPlan): View
    {
        return view('church-admin.reading-plans.form', ['church' => $church, 'plan' => $readingPlan]);
    }

    public function update(ReadingPlanRequest $request, Church $church, ReadingPlan $readingPlan): RedirectResponse
    {
        $entries = $request->entries();
        $readingPlan->update([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'is_published' => $request->boolean('is_published'),
            'entries' => $entries,
            'days_count' => count($entries),
        ]);

        return redirect()->route('church-admin.reading-plans.index', $church)->with('success', __('Reading plan updated.'));
    }

    public function destroy(Church $church, ReadingPlan $readingPlan): RedirectResponse
    {
        $readingPlan->delete();

        return back()->with('success', __('Reading plan deleted.'));
    }
}
