<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
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
    public const RANGES = [30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last 12 months'];

    public function __construct(protected AnalyticsService $analytics) {}

    public function index(Request $request, Church $church): View
    {
        $days = $this->days($request);

        return view('church-admin.analytics.index', [
            'church' => $church,
            'days' => $days,
            'data' => $this->analytics->churchAnalytics($church, $days, $request->user()->canInChurch($church, 'giving.view')),
        ]);
    }

    /**
     * Printable PDF summary for church boards / council meetings.
     */
    public function pdf(Request $request, Church $church): Response
    {
        $days = $this->days($request);
        $data = $this->analytics->churchAnalytics($church, $days, $request->user()->canInChurch($church, 'giving.view'));

        return Pdf::loadView('reports.church-analytics', ['church' => $church, 'data' => $data, 'days' => $days])
            ->setPaper('a4')
            ->download('analytics-'.$church->slug.'-'.now()->format('Ymd').'.pdf');
    }

    public function export(Request $request, Church $church, string $dataset): StreamedResponse
    {
        $from = now()->subDays($this->days($request))->startOfDay();
        $name = $dataset.'-'.$church->slug.'-'.now()->format('Ymd').'.csv';

        return match ($dataset) {
            'members' => CsvExport::download($name, ['Joined', 'Stage', 'Status', 'Branch'],
                DB::table('subscriptions')->leftJoin('branches', 'branches.id', '=', 'subscriptions.branch_id')
                    ->where('subscriptions.church_id', $church->id)->where('subscriptions.subscribed_at', '>=', $from)->orderBy('subscriptions.subscribed_at')
                    ->select('subscriptions.subscribed_at', 'subscriptions.stage', 'subscriptions.status', 'branches.name')->cursor()
                    ->map(fn ($r) => [$r->subscribed_at, $r->stage, $r->status, $r->name])),
            'engagement' => CsvExport::download($name, ['Week', 'Views', 'Listens / watches', 'Downloads'],
                DB::table('content_views')->where('church_id', $church->id)->where('created_at', '>=', $from)
                    ->selectRaw("DATE(DATE_SUB(created_at, INTERVAL WEEKDAY(created_at) DAY)) wk, SUM(action = 'view') v, SUM(action IN ('listen','watch')) l, SUM(action = 'download') d")
                    ->groupBy('wk')->orderBy('wk')->get()->map(fn ($r) => [$r->wk, $r->v, $r->l, $r->d])),
            'attendance' => CsvExport::download($name, ['Date', 'Headcount', 'Members checked in', 'Guests'],
                DB::table('attendance_records')->where('church_id', $church->id)->where('service_date', '>=', $from)
                    ->selectRaw('service_date, SUM(headcount) total, COUNT(user_id) members, SUM(CASE WHEN user_id IS NULL THEN headcount ELSE 0 END) guests')
                    ->groupBy('service_date')->orderBy('service_date')->get()->map(fn ($r) => [$r->service_date, $r->total, $r->members, $r->guests])),
            'giving' => $this->givingExport($request, $church, $from, $name),
            'notifications' => CsvExport::download($name, ['Date', 'Channel', 'Sent', 'Failed', 'Cost (UGX)'],
                DB::table('notification_deliveries')->where('church_id', $church->id)->where('created_at', '>=', $from)
                    ->selectRaw("DATE(created_at) d, channel, SUM(status = 'sent') s, SUM(status = 'failed') f, SUM(cost) c")
                    ->groupBy('d', 'channel')->orderBy('d')->get()->map(fn ($r) => [$r->d, $r->channel, $r->s, $r->f, $r->c])),
            default => abort(404),
        };
    }

    protected function givingExport(Request $request, Church $church, $from, string $name): StreamedResponse
    {
        abort_unless($request->user()->canInChurch($church, 'giving.view'), 403);

        return CsvExport::download($name, ['Week', 'Gifts', 'Givers', 'Total (UGX)'],
            DB::table('donations')->where('church_id', $church->id)->where('status', 'succeeded')->where('paid_at', '>=', $from)
                ->selectRaw('DATE(DATE_SUB(paid_at, INTERVAL WEEKDAY(paid_at) DAY)) wk, COUNT(*) n, COUNT(DISTINCT user_id) g, SUM(amount) t')
                ->groupBy('wk')->orderBy('wk')->get()->map(fn ($r) => [$r->wk, $r->n, $r->g, $r->t]));
    }

    protected function days(Request $request): int
    {
        $days = (int) $request->query('days', 90);

        return array_key_exists($days, self::RANGES) ? $days : 90;
    }
}
