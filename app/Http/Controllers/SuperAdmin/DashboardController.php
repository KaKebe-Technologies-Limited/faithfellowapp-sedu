<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('super-admin.dashboard', [
            'stats' => [
                'users' => User::count(),
                'new_users' => User::where('created_at', '>=', now()->subDays(30))->count(),
                'messages' => ContactMessage::where('status', 'new')->count(),
            ],
            'recentUsers' => User::latest()->take(6)->get(),
            'recentActivity' => AuditLog::with('user')->latest('created_at')->take(8)->get(),
        ]);
    }
}
