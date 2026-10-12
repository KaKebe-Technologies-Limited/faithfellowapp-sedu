<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\BankStatementLine;
use App\Models\Church;
use App\Models\Donation;
use App\Services\DonationService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Match bank statement lines (CSV upload) to gifts.
 *
 * Auto-match: same amount AND (reference contains our FF… reference, OR a
 * single pending bank transfer of that amount within ±3 days).
 */
class ReconciliationController extends Controller
{
    public function __construct(protected DonationService $donations) {}

    public function index(Request $request, Church $church): View
    {
        $lines = BankStatementLine::forChurch($church)->with('donation')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')), fn ($q) => $q->where('status', 'unmatched'))
            ->latest('transaction_date')->paginate(30)->withQueryString();

        return view('church-admin.giving.reconciliation', [
            'church' => $church,
            'lines' => $lines,
            'pendingBank' => $church->donations()->with('user')->where('method', 'bank')->whereIn('status', ['pending', 'processing'])->latest()->take(50)->get(),
            'counts' => BankStatementLine::forChurch($church)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    /**
     * CSV columns: date, description, reference, amount (header row optional).
     */
    public function import(Request $request, Church $church): RedirectResponse
    {
        $request->validate(['statement' => ['required', 'file', 'mimes:csv,txt', 'max:4096']]);
        $batch = 'B'.now()->format('ymdHis').Str::upper(Str::random(3));
        $imported = 0;

        $handle = fopen($request->file('statement')->getRealPath(), 'r');
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 4) {
                continue;
            }
            [$date, $description, $reference, $amount] = array_map('trim', $row);
            $amount = (float) preg_replace('/[^\d.\-]/', '', $amount);
            if ($amount <= 0 || ! strtotime($date)) {
                continue; // skips header row, debits and junk lines
            }
            BankStatementLine::create([
                'church_id' => $church->id, 'batch' => $batch, 'transaction_date' => Carbon::parse($date),
                'description' => Str::limit($description, 250), 'reference' => Str::limit($reference, 190), 'amount' => $amount,
            ]);
            $imported++;
        }
        fclose($handle);

        $matched = $this->autoMatch($church, $batch);
        Audit::log('giving.statement_imported', $church, "$imported lines, $matched matched");

        return back()->with('success', __(':i lines imported, :m matched automatically.', ['i' => $imported, 'm' => $matched]));
    }

    public function match(Request $request, Church $church, BankStatementLine $line): RedirectResponse
    {
        abort_unless($line->church_id === $church->id, 404);
        $data = $request->validate(['donation_id' => ['nullable', 'integer'], 'action' => ['nullable', 'in:ignore,unmatch']]);

        if (($data['action'] ?? null) === 'ignore') {
            $line->update(['status' => 'ignored']);
        } elseif (($data['action'] ?? null) === 'unmatch') {
            $line->update(['status' => 'unmatched', 'donation_id' => null]);
        } else {
            $donation = Donation::where('church_id', $church->id)->findOrFail($data['donation_id']);
            $this->link($line, $donation);
        }

        return back()->with('success', __('Statement line updated.'));
    }

    protected function autoMatch(Church $church, string $batch): int
    {
        $matched = 0;
        BankStatementLine::forChurch($church)->where('batch', $batch)->where('status', 'unmatched')->each(function (BankStatementLine $line) use ($church, &$matched) {
            $text = strtoupper($line->reference.' '.$line->description);
            $donation = null;

            if (preg_match('/FF\d{6}[A-Z0-9]{8}/', $text, $m)) {
                $donation = Donation::where('church_id', $church->id)->where('reference', $m[0])->where('amount', $line->amount)->first();
            }
            if (! $donation) {
                $candidates = Donation::where('church_id', $church->id)->where('method', 'bank')->whereIn('status', ['pending', 'processing'])
                    ->where('amount', $line->amount)->whereBetween('created_at', [$line->transaction_date->copy()->subDays(3), $line->transaction_date->copy()->addDays(3)->endOfDay()])
                    ->limit(2)->get();
                $donation = $candidates->count() === 1 ? $candidates->first() : null;
            }

            if ($donation) {
                $this->link($line, $donation);
                $matched++;
            }
        });

        return $matched;
    }

    protected function link(BankStatementLine $line, Donation $donation): void
    {
        $line->update(['status' => 'matched', 'donation_id' => $donation->id]);
        if (in_array($donation->status, ['pending', 'processing'], true)) {
            $this->donations->markSucceeded($donation, null, ['bank_statement_line' => $line->id]);
        }
        $donation->update(['reconciled_at' => now()]);
    }
}
