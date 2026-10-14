<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Comment;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Moderation queue for comments on posts, sermons, campaigns, testimonies and prayers.
 */
class CommentController extends Controller
{
    public function index(Request $request, Church $church): View
    {
        $status = $request->query('status');

        return view('church-admin.comments.index', [
            'church' => $church,
            'status' => $status,
            'comments' => $church->comments()->with(['user.media', 'commentable'])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($request->filled('q'), fn ($q) => $q->where('body', 'like', '%'.$request->query('q').'%'))
                ->latest()->paginate(30)->withQueryString(),
            'counts' => $church->comments()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function update(Request $request, Church $church, Comment $comment): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['visible', 'hidden', 'flagged'])]]);
        $comment->update($data);
        Audit::log('comment.'.$data['status'], $comment);

        return back()->with('success', __('Comment updated.'));
    }

    public function destroy(Church $church, Comment $comment): RedirectResponse
    {
        $comment->delete();
        Audit::log('comment.deleted', $comment);

        return back()->with('success', __('Comment deleted.'));
    }
}
