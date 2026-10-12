<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\User;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service attendance: QR / geofence self check-in, manual entry, trends, export.
 */
class AttendanceController extends Controller
{
    public function index(Request $request, Church $church): View
    {
        $date = Carbon::parse($request->query('date', today()->toDateString()));

        $weekly = $church->attendanceRecords()
            ->where('service_date', '>=', today()->subWeeks(12)->startOfWeek())
            ->selectRaw('YEARWEEK(service_date, 0) wk, MIN(service_date) d, SUM(headcount) total')
            ->groupBy('wk')->orderBy('wk')->get();

        return view('church-admin.attendance.index', [
            'church' => $church,
            'date' => $date,
            'records' => $church->attendanceRecords()->with(['user.media', 'serviceTime'])->whereDate('service_date', $date)->latest()->get(),
            'chart' => [
                'type' => 'bar',
                'data' => [
                    'labels' => $weekly->map(fn ($w) => Carbon::parse($w->d)->format('j M'))->all(),
                    'datasets' => [['label' => __('Attendance'), 'data' => $weekly->pluck('total')->map(fn ($v) => (int) $v)->all()]],
                ],
            ],
            'methods' => $church->attendanceRecords()->where('service_date', '>=', today()->subWeeks(12))
                ->selectRaw('method, SUM(headcount) total')->groupBy('method')->pluck('total', 'method'),
            'serviceTimes' => $church->serviceTimes()->orderBy('day_of_week')->orderBy('start_time')->get(),
            'people' => User::whereIn('id', $church->subscriptions()->where('status', 'active')->select('user_id'))->orderBy('name')->limit(1000)->pluck('name', 'id'),
            'absentees' => $this->absentees($church),
        ]);
    }

    /**
     * Printable QR poster for today's services. The signed link expires at midnight.
     */
    public function qr(Church $church): View
    {
        $url = URL::temporarySignedRoute('check-in.show', today()->endOfDay(), ['church' => $church]);

        return view('church-admin.attendance.qr', [
            'church' => $church,
            'url' => $url,
            'qr' => QrCode::format('svg')->size(420)->margin(1)->errorCorrection('M')->generate($url),
        ]);
    }

    public function store(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate([
            'service_date' => ['required', 'date', 'before_or_equal:today'],
            'service_time_id' => ['nullable', Rule::exists('service_times', 'id')->where('church_id', $church->id)],
            'user_id' => ['nullable', 'required_without:guest_name', Rule::exists('subscriptions', 'user_id')->where('church_id', $church->id)],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'headcount' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        if (! empty($data['user_id'])) {
            AttendanceRecord::updateOrCreate(
                ['church_id' => $church->id, 'user_id' => $data['user_id'], 'service_date' => $data['service_date']],
                ['method' => 'manual', 'service_time_id' => $data['service_time_id'] ?? null, 'recorded_by' => $request->user()->id],
            );
        } else {
            // Guests and bulk head counts (e.g. children's church) are stored as one row.
            $church->attendanceRecords()->create([
                ...$data, 'method' => 'manual', 'headcount' => $data['headcount'] ?? 1, 'recorded_by' => $request->user()->id,
            ]);
        }

        return back()->with('success', __('Attendance recorded.'));
    }

    public function destroy(Church $church, AttendanceRecord $attendanceRecord): RedirectResponse
    {
        $attendanceRecord->delete();

        return back()->with('success', __('Record removed.'));
    }

    public function export(Request $request, Church $church): StreamedResponse
    {
        $from = Carbon::parse($request->query('from', today()->subMonths(3)->toDateString()));
        $rows = $church->attendanceRecords()->with(['user', 'serviceTime'])->where('service_date', '>=', $from)->orderBy('service_date')->lazy()
            ->map(fn ($r) => [$r->service_date->toDateString(), $r->user?->name ?? $r->guest_name, $r->user?->phone, $r->serviceTime?->name, $r->method, $r->headcount]);

        return CsvExport::download('attendance-'.$church->slug.'-'.now()->format('Ymd').'.csv', ['Date', 'Name', 'Phone', 'Service', 'Method', 'Headcount'], $rows);
    }

    /**
     * Regular attenders (3+ check-ins in the 8 weeks before) who have missed the last 3 weeks.
     */
    protected function absentees(Church $church)
    {
        $recent = $church->attendanceRecords()->whereNotNull('user_id')->where('service_date', '>=', today()->subWeeks(3))->select('user_id');

        return User::with('media')
            ->whereIn('id', $church->attendanceRecords()->whereNotNull('user_id')
                ->whereBetween('service_date', [today()->subWeeks(11), today()->subWeeks(3)])
                ->groupBy('user_id')->havingRaw('count(*) >= 3')->select('user_id'))
            ->whereNotIn('id', $recent)->orderBy('name')->take(20)->get();
    }
}
