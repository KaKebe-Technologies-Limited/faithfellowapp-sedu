<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Opt-in member directory. Only members of a church can browse it, and only
 * people who chose "List me in member directories" appear.
 */
class DirectoryController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $churches = Church::whereIn('id', $user->memberChurchIds())->orderBy('name')->get(['id', 'name', 'slug']);
        $church = $request->filled('church')
            ? $churches->firstWhere('slug', $request->query('church'))
            : $churches->first();

        abort_if($request->filled('church') && ! $church, 403);

        $members = $church
            ? User::query()
                ->with('media')
                ->where('directory_opt_in', true)
                ->whereHas('subscriptions', fn ($q) => $q->where('church_id', $church->id)->where('status', 'active'))
                ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
                ->orderBy('name')
                ->paginate(24)
                ->withQueryString()
            : null;

        return view('member.directory', compact('churches', 'church', 'members', 'user'));
    }
}
