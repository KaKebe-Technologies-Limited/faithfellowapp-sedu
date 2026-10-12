<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Church;
use App\Services\AnalyticsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Church $church, AnalyticsService $analytics): View
    {
        $church->load('media');

        return view('church-admin.dashboard', [
            'church' => $church,
            'stats' => $analytics->churchSummary($church),
            'dashboard' => method_exists($analytics, 'churchDashboard') ? $analytics->churchDashboard($church) : [],
            'checklist' => $analytics->setupChecklist($church),
            'activity' => AuditLog::forChurch($church->id)->with('user')->latest('created_at')->take(8)->get(),
        ]);
    }
}
