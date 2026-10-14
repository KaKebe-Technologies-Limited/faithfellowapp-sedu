<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Church;
use App\Models\Donation;
use App\Models\GivingChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * The church's financial records for accounting and monitoring: what came in over a period, clustered by what it was given for
 * (the church's own giving reasons, fundraising campaigns, event tickets), by payment method and over time, what is still
 * pending, refunded or waiting to be paid out to the church, and the ledger behind every number.
 *
 * Money received counts gifts that succeeded, by the day they were paid. Fees are what the payment company kept; net is what
 * the church receives.
 */
class FinancialRecords
{
    public const PERIODS = [
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'this_quarter' => 'This quarter',
        'this_year' => 'This year',
        'last_year' => 'Last year',
        'last_30' => 'Last 30 days',
        'custom' => 'Choose dates',
    ];

    /** @return array{0: Carbon, 1: Carbon, 2: string} from, to, the period's key */
    public static function period(?string $key, ?string $from = null, ?string $to = null): array
    {
        $key = array_key_exists((string) $key, self::PERIODS) ? $key : 'this_month';
        $now = now();

        [$start, $end] = match ($key) {
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$now->copy()->firstOfQuarter(), $now->copy()->lastOfQuarter()->endOfDay()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year' => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'last_30' => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            'custom' => [self::date($from, $now->copy()->startOfMonth())->startOfDay(), self::date($to, $now)->endOfDay()],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };

        return $end->lt($start) ? [$end->copy()->startOfDay(), $start->copy()->endOfDay(), $key] : [$start, $end, $key];
    }

    protected static function date(?string $value, Carbon $fallback): Carbon
    {
        try {
            return $value ? Carbon::parse($value) : $fallback->copy();
        } catch (\Throwable) {
            return $fallback->copy();
        }
    }

    /** Gifts of this church that match the filters (reason, method, campaign), before the status and date are applied. */
    public function query(Church $church, array $filters = []): Builder
    {
        return Donation::query()->where('church_id', $church->id)
            ->when($filters['reason'] ?? null, fn ($q, $v) => str_starts_with((string) $v, 'campaign:')
                ? $q->where('campaign_id', (int) substr((string) $v, 9))
                : $q->where('giving_reason_id', (int) $v))
            ->when($filters['method'] ?? null, fn ($q, $v) => $q->where('method', $v))
            ->when($filters['campaign'] ?? null, fn ($q, $v) => $q->where('campaign_id', $v));
    }

    /** Money received in the period: succeeded gifts paid between `$from` and `$to`. */
    public function received(Church $church, Carbon $from, Carbon $to, array $filters = []): Builder
    {
        return $this->query($church, $filters)->where('status', 'succeeded')->whereBetween('paid_at', [$from, $to]);
    }

    /**
     * @param  array<string, mixed>  $filters  reason, method, campaign
     * @return array<string, mixed>
     */
    public function build(Church $church, Carbon $from, Carbon $to, array $filters = []): array
    {
        $totals = $this->received($church, $from, $to, $filters)
            ->selectRaw('COUNT(*) as gifts, COALESCE(SUM(amount), 0) as gross, COALESCE(SUM(fee), 0) as fees, COUNT(DISTINCT COALESCE(CAST(user_id AS CHAR), giver_name, CAST(id AS CHAR))) as givers')->first();
        $gross = (float) $totals->gross;
        $fees = (float) $totals->fees;
        $gifts = (int) $totals->gifts;

        $inPeriod = fn () => $this->query($church, $filters)->whereBetween('created_at', [$from, $to]);
        $pending = $inPeriod()->whereIn('status', ['pending', 'processing'])->selectRaw('COUNT(*) as n, COALESCE(SUM(amount), 0) as total')->first();
        $failed = $inPeriod()->whereIn('status', ['failed', 'cancelled'])->count();
        $refunded = $this->query($church, $filters)->where('status', 'refunded')->whereBetween('refunded_at', [$from, $to])->selectRaw('COUNT(*) as n, COALESCE(SUM(amount), 0) as total')->first();

        // Collected through the payment company but not yet paid out to the church's own account.
        $awaiting = $this->query($church, [])->where('status', 'succeeded')->whereNull('payout_id')->whereIn('method', GivingChannel::ONLINE)
            ->selectRaw('COALESCE(SUM(amount - fee), 0) as total, COUNT(*) as n')->first();

        return [
            'currency' => config('faithfellows.currency'),
            'from' => $from, 'to' => $to,
            'gross' => $gross, 'fees' => $fees, 'net' => $gross - $fees, 'gifts' => $gifts, 'givers' => (int) $totals->givers,
            'average' => $gifts ? $gross / $gifts : 0.0,
            'pending' => ['count' => (int) $pending->n, 'amount' => (float) $pending->total],
            'failed' => $failed,
            'refunded' => ['count' => (int) $refunded->n, 'amount' => (float) $refunded->total],
            'awaiting_payout' => ['count' => (int) $awaiting->n, 'amount' => (float) $awaiting->total],
            'by_reason' => $this->byReason($church, $from, $to, $filters, $gross),
            'by_method' => $this->byMethod($church, $from, $to, $filters, $gross),
            'series' => $this->series($church, $from, $to, $filters),
        ];
    }

    /**
     * The clusters: one line for each thing people gave for. A gift to a fundraising campaign counts under that campaign; a gift
     * with a reason under the reason's name; older gifts under their platform category.
     *
     * @return Collection<int, array{label: string, kind: string, gifts: int, gross: float, fees: float, net: float, share: float, average: float}>
     */
    protected function byReason(Church $church, Carbon $from, Carbon $to, array $filters, float $gross): Collection
    {
        // Before the database update that added the reason column, gifts are grouped by their category only.
        $reason = Schema::hasColumn('donations', 'reason') ? 'reason' : 'NULL';
        $rows = $this->received($church, $from, $to, $filters)
            ->selectRaw("category, {$reason} as reason, campaign_id, COUNT(*) as gifts, SUM(amount) as gross, SUM(fee) as fees")->groupBy('category', 'campaign_id', ...($reason === 'reason' ? ['reason'] : []))->get();
        $campaigns = Campaign::whereIn('id', $rows->pluck('campaign_id')->filter()->unique())->pluck('title', 'id');

        return $rows->map(function ($r) use ($campaigns) {
            [$label, $kind] = match (true) {
                $r->campaign_id !== null => [__('Fundraising: :title', ['title' => $campaigns[$r->campaign_id] ?? __('a campaign')]), 'campaign'],
                filled($r->reason) => [$r->reason, 'reason'],
                $r->category === 'event' => [__('Event tickets'), 'tickets'],
                default => [config('faithfellows.giving_categories.'.$r->category, ucfirst((string) $r->category)), 'reason'],
            };

            return ['label' => $label, 'kind' => $kind, 'gifts' => (int) $r->gifts, 'gross' => (float) $r->gross, 'fees' => (float) $r->fees];
        })->groupBy('label')->map(function (Collection $group, string $label) use ($gross) {
            $sum = fn (string $k) => (float) $group->sum($k);

            return [
                'label' => $label, 'kind' => $group->first()['kind'], 'gifts' => (int) $group->sum('gifts'), 'gross' => $sum('gross'), 'fees' => $sum('fees'),
                'net' => $sum('gross') - $sum('fees'), 'share' => $gross > 0 ? round($sum('gross') / $gross * 100, 1) : 0.0,
                'average' => $group->sum('gifts') ? $sum('gross') / $group->sum('gifts') : 0.0,
            ];
        })->sortByDesc('gross')->values();
    }

    /** @return Collection<int, array{label: string, gifts: int, gross: float, fees: float, net: float, share: float}> */
    protected function byMethod(Church $church, Carbon $from, Carbon $to, array $filters, float $gross): Collection
    {
        return $this->received($church, $from, $to, $filters)->selectRaw('method, COUNT(*) as gifts, SUM(amount) as gross, SUM(fee) as fees')->groupBy('method')->orderByDesc('gross')->get()
            ->map(fn ($r) => [
                'label' => config('faithfellows.payment_methods.'.$r->method, $r->method), 'gifts' => (int) $r->gifts, 'gross' => (float) $r->gross, 'fees' => (float) $r->fees,
                'net' => (float) $r->gross - (float) $r->fees, 'share' => $gross > 0 ? round((float) $r->gross / $gross * 100, 1) : 0.0,
            ])->values();
    }

    /**
     * Money received over time, a bar for each day (or each month for long periods), with the empty ones filled in as zero.
     *
     * @return array{type: string, data: array<string, mixed>}
     */
    protected function series(Church $church, Carbon $from, Carbon $to, array $filters): array
    {
        $daily = $from->diffInDays($to) <= 62;
        $format = $daily ? '%Y-%m-%d' : '%Y-%m';
        $totals = $this->received($church, $from, $to, $filters)->selectRaw("DATE_FORMAT(paid_at, '{$format}') as bucket, SUM(amount) as total")->groupBy('bucket')->pluck('total', 'bucket');

        $labels = [];
        $values = [];
        for ($cursor = $from->copy()->startOf($daily ? 'day' : 'month'); $cursor->lte($to); $daily ? $cursor->addDay() : $cursor->addMonth()) {
            $key = $cursor->format($daily ? 'Y-m-d' : 'Y-m');
            $labels[] = $cursor->format($daily ? 'j M' : 'M Y');
            $values[] = (float) ($totals[$key] ?? 0);
        }

        return ['type' => 'bar', 'data' => ['labels' => $labels, 'datasets' => [['label' => __('Received (:cur)', ['cur' => config('faithfellows.currency')]), 'data' => $values]]]];
    }

    /** The ledger: every gift behind the numbers, oldest first. */
    public function ledger(Church $church, Carbon $from, Carbon $to, array $filters = []): Builder
    {
        return $this->received($church, $from, $to, $filters)->with(['user', 'campaign'])->orderBy('paid_at');
    }

    /** The filter choices: the church's reasons and fundraising campaigns. */
    public function choices(Church $church): array
    {
        return [
            'reasons' => $church->givingReasons()->ordered()->pluck('name', 'id'),
            'campaigns' => $church->campaigns()->orderBy('title')->pluck('title', 'id'),
        ];
    }
}
