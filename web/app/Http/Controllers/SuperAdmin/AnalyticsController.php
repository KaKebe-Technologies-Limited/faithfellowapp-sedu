<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\ChurchAdmin\AnalyticsController as ChurchAnalytics;
use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use App\Support\CsvExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function __construct(protected AnalyticsService $analytics) {}

    public function index(Request $request): View
    {
        $days = $this->days($request);

        return view('super-admin.analytics', ['days' => $days, 'data' => $this->analytics->platformAnalytics($days)]);
    }

    public function pdf(Request $request): Response
    {
        $days = $this->days($request);

        return Pdf::loadView('reports.platform-analytics', ['data' => $this->analytics->platformAnalytics($days), 'days' => $days])
            ->setPaper('a4')->download('faithfellows-platform-'.now()->format('Ymd').'.pdf');
    }

    /**
     * Per-church summary (members, staff, giving, plan) for investor / board reporting.
     */
    public function export(Request $request): StreamedResponse
    {
        $from = now()->subDays($this->days($request));

        return CsvExport::download('churches-summary-'.now()->format('Ymd').'.csv', ['Church', 'City', 'District', 'Status', 'Billing', 'Plan', 'Members', 'New members', 'Giving (UGX)', 'Live services', 'Approved'],
            DB::table('churches')->leftJoin('plans', 'plans.id', '=', 'churches.plan_id')->whereNull('churches.deleted_at')->orderBy('churches.name')
                ->select('churches.*', 'plans.name as plan_name')->cursor()
                ->map(fn ($c) => [
                    $c->name, $c->city, $c->district, $c->status, $c->billing_status, $c->plan_name,
                    DB::table('subscriptions')->where('church_id', $c->id)->where('status', 'active')->count(),
                    DB::table('subscriptions')->where('church_id', $c->id)->where('subscribed_at', '>=', $from)->count(),
                    DB::table('donations')->where('church_id', $c->id)->where('status', 'succeeded')->where('paid_at', '>=', $from)->sum('amount'),
                    DB::table('live_streams')->where('church_id', $c->id)->where('started_at', '>=', $from)->count(),
                    $c->approved_at,
                ]));
    }

    protected function days(Request $request): int
    {
        $days = (int) $request->query('days', 90);

        return array_key_exists($days, ChurchAnalytics::RANGES) ? $days : 90;
    }
}
