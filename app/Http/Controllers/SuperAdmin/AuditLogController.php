<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Church;
use App\Support\CsvExport;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        return view('super-admin.audit-logs', [
            'logs' => $this->query($request)->with(['user', 'impersonator'])->paginate(50)->withQueryString(),
            'churches' => Church::orderBy('name')->pluck('name', 'id'),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        return CsvExport::download('audit-log-'.now()->format('Ymd').'.csv', ['Time', 'Church', 'User', 'Impersonated by', 'Action', 'Subject', 'Description', 'IP'],
            $this->query($request)->with(['user', 'impersonator'])->lazy()->map(fn (AuditLog $l) => [
                $l->created_at, $l->church_id, $l->user?->email, $l->impersonator?->email, $l->action,
                $l->auditable_type ? class_basename($l->auditable_type).'#'.$l->auditable_id : '', $l->description, $l->ip_address,
            ]));
    }

    protected function query(Request $request)
    {
        return AuditLog::query()
            ->when($request->filled('church'), fn ($q) => $q->where('church_id', $request->query('church')))
            ->when($request->query('platform'), fn ($q) => $q->whereNull('church_id'))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->query('action')))
            ->when($request->filled('q'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('email', 'like', '%'.$request->query('q').'%')->orWhere('name', 'like', '%'.$request->query('q').'%')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<', $request->date('to')?->addDay()))
            ->latest('created_at');
    }
}
