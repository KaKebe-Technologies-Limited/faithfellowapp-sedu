<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Church;
use App\Models\ContactMessage;
use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(AnalyticsService $analytics): View
    {
        $stats = [
            'churches' => Church::where('status', 'active')->count(),
            'pending' => Church::where('status', 'pending')->count(),
            'users' => User::count(),
            'new_users' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'messages' => ContactMessage::where('status', 'new')->count(),
        ];

        if (method_exists($analytics, 'platformHeadline')) {
            $stats = array_merge($stats, $analytics->platformHeadline());
        }

        return view('super-admin.dashboard', [
            'stats' => $stats,
            'pendingChurchList' => Church::where('status', 'pending')->with('media')->latest()->take(5)->get(),
            'recentUsers' => User::with('media')->latest()->take(6)->get(),
            'recentActivity' => AuditLog::with('user')->latest('created_at')->take(8)->get(),
        ]);
    }
}
