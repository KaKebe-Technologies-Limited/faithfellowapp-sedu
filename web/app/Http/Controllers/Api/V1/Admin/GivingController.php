<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Donation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Giving at a glance for treasurers and pastors: what came in this week, month and year, by category and method,
 * and the latest gifts. Recording cash, refunds and payouts stay on the website.
 */
class GivingController extends Controller
{
    public function summary(Church $church): JsonResponse
    {
        $paid = fn () => Donation::where('church_id', $church->id)->succeeded();
        $sum = fn ($from) => (float) $paid()->where('paid_at', '>=', $from)->sum('amount');

        return response()->json(['data' => [
            'currency' => config('faithfellows.currency'),
            'today' => $sum(now()->startOfDay()),
            'week' => $sum(now()->startOfWeek()),
            'month' => $sum(now()->startOfMonth()),
            'year' => $sum(now()->startOfYear()),
            'last_month' => (float) $paid()->whereBetween('paid_at', [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()])->sum('amount'),
            'gifts_month' => (int) $paid()->where('paid_at', '>=', now()->startOfMonth())->count(),
            'by_category' => $paid()->where('paid_at', '>=', now()->startOfMonth())->select('category', DB::raw('SUM(amount) as total'))->groupBy('category')->orderByDesc('total')->get()
                ->map(fn ($r) => ['key' => $r->category, 'label' => config('faithfellows.giving_categories.'.$r->category, $r->category), 'total' => (float) $r->total])->values(),
            'by_method' => $paid()->where('paid_at', '>=', now()->startOfMonth())->select('method', DB::raw('SUM(amount) as total'))->groupBy('method')->orderByDesc('total')->get()
                ->map(fn ($r) => ['key' => $r->method, 'label' => config('faithfellows.payment_methods.'.$r->method, $r->method), 'total' => (float) $r->total])->values(),
            'pending' => (int) Donation::where('church_id', $church->id)->whereIn('status', ['pending', 'processing'])->count(),
        ]]);
    }

    public function index(Request $request, Church $church): JsonResponse
    {
        $page = Donation::where('church_id', $church->id)->with(['user', 'campaign'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')), fn ($q) => $q->whereIn('status', ['succeeded', 'pending', 'processing', 'refunded']))
            ->latest()->paginate(30)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (Donation $d) => [
                'reference' => $d->reference, 'giver' => $d->display_name, 'amount' => (float) $d->amount, 'currency' => $d->currency, 'status' => $d->status,
                'category' => $d->category_label, 'campaign' => $d->campaign?->title, 'method' => $d->method_label, 'at' => ($d->paid_at ?? $d->created_at)?->toIso8601String(),
            ])->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }
}
