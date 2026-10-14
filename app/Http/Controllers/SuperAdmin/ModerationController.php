<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\ContentReport;
use App\Services\ModerationService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Platform-wide moderation: reports churches haven't handled, repeat offenders.
 */
class ModerationController extends Controller
{
    public function __construct(protected ModerationService $moderation) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');

        return view('super-admin.moderation.index', [
            'status' => $status,
            'reports' => ContentReport::with(['reportable', 'reporter', 'church', 'handler'])
                ->where('status', $status)
                ->when($request->query('stale'), fn ($q) => $q->where('created_at', '<', now()->subDays(2)))
                ->latest()->paginate(30)->withQueryString(),
            'counts' => ContentReport::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'stale' => ContentReport::where('status', 'open')->where('created_at', '<', now()->subDays(2))->count(),
            'offenders' => \DB::table('content_reports')->where('content_reports.status', 'actioned')
                ->join('comments', fn ($j) => $j->on('comments.id', '=', 'content_reports.reportable_id')->where('content_reports.reportable_type', Comment::class))
                ->join('users', 'users.id', '=', 'comments.user_id')
                ->selectRaw('users.id, users.name, users.status, COUNT(*) n')->groupBy('users.id', 'users.name', 'users.status')
                ->having('n', '>=', 2)->orderByDesc('n')->take(10)->get(),
        ]);
    }

    public function update(Request $request, ContentReport $contentReport): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', Rule::in(['hide', 'dismiss', 'suspend'])]])['action'];

        if ($action === 'suspend') {
            $author = $this->moderation->author($contentReport->reportable);
            abort_unless($author && ! $author->isSuperAdmin(), 422);
            $author->update(['status' => 'suspended']);
            Audit::log('user.suspended', $author, __('Suspended after moderation report #:id', ['id' => $contentReport->id]));
            $this->moderation->hide($contentReport, $request->user());

            return back()->with('success', __(':name has been suspended and the content hidden.', ['name' => $author->name]));
        }

        $action === 'hide' ? $this->moderation->hide($contentReport, $request->user()) : $this->moderation->dismiss($contentReport, $request->user());

        return back()->with('success', $action === 'hide' ? __('Content hidden.') : __('Report dismissed.'));
    }
}
