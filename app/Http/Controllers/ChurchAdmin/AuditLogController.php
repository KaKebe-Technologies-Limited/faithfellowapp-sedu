<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Church;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Request $request, Church $church): View
    {
        $this->authorize('manageStaff', $church);

        $logs = AuditLog::forChurch($church->id)
            ->with(['user', 'impersonator'])
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', $request->string('action').'%'))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('church-admin.audit-logs', compact('church', 'logs'));
    }
}
