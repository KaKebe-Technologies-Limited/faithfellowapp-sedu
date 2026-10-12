<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\ContentReport;
use App\Services\ModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The church's own moderation queue for member reports.
 */
class ReportController extends Controller
{
    public function __construct(protected ModerationService $moderation) {}

    public function index(Request $request, Church $church): View
    {
        $status = $request->query('status', 'open');

        return view('church-admin.reports.index', [
            'church' => $church,
            'status' => $status,
            'reports' => $church->contentReports()->with(['reportable', 'reporter', 'handler'])
                ->where('status', $status)->latest()->paginate(25)->withQueryString(),
        ]);
    }

    public function update(Request $request, Church $church, ContentReport $contentReport): RedirectResponse
    {
        $action = $request->validate(['action' => ['required', Rule::in(['hide', 'dismiss'])]])['action'];
        $action === 'hide' ? $this->moderation->hide($contentReport, $request->user()) : $this->moderation->dismiss($contentReport, $request->user());

        return back()->with('success', $action === 'hide' ? __('Content hidden.') : __('Report dismissed.'));
    }
}
