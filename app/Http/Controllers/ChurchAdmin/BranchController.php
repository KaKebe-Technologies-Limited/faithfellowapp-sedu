<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Church\BranchRequest;
use App\Models\Branch;
use App\Models\Church;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Church $church): View
    {
        $this->authorize('update', $church);

        return view('church-admin.branches.index', [
            'church' => $church,
            'branches' => $church->branches()->withCount(['serviceTimes', 'staff'])->orderByDesc('is_main')->orderBy('name')->paginate(20),
        ]);
    }

    public function create(Church $church): View
    {
        $this->authorize('update', $church);

        return view('church-admin.branches.form', ['church' => $church, 'branch' => new Branch]);
    }

    public function store(BranchRequest $request, Church $church): RedirectResponse
    {
        DB::transaction(function () use ($request, $church) {
            if ($request->boolean('is_main')) {
                $church->branches()->update(['is_main' => false]);
            }
            $base = Str::slug($request->input('name')) ?: 'branch';
            $slug = $base;
            for ($i = 2; $church->branches()->withTrashed()->where('slug', $slug)->exists(); $i++) {
                $slug = $base.'-'.$i;
            }
            $church->branches()->create([...$request->validated(), 'slug' => $slug]);
        });

        return redirect()->route('church-admin.branches.index', $church)->with('success', __('Branch added.'));
    }

    public function edit(Church $church, Branch $branch): View
    {
        $this->authorize('update', $church);

        return view('church-admin.branches.form', compact('church', 'branch'));
    }

    public function update(BranchRequest $request, Church $church, Branch $branch): RedirectResponse
    {
        DB::transaction(function () use ($request, $church, $branch) {
            if ($request->boolean('is_main')) {
                $church->branches()->whereKeyNot($branch->id)->update(['is_main' => false]);
            }
            $branch->update($request->validated());
        });

        return redirect()->route('church-admin.branches.index', $church)->with('success', __('Branch updated.'));
    }

    public function destroy(Church $church, Branch $branch): RedirectResponse
    {
        $this->authorize('update', $church);
        abort_if($branch->is_main, 422, __('The main branch cannot be deleted.'));
        $branch->delete();

        return back()->with('success', __('Branch removed.'));
    }
}
