<?php

// Gives the demo member and the demo church a little life for the documentation screenshots (development database only).
use App\Models\Church;
use App\Models\MemberActivity;
use App\Models\User;
use App\Services\BillingService;

$u = User::where('email', 'member@faithfellows.test')->first();
$u->update(['phone' => null, 'bio' => null]);
MemberActivity::where('user_id', $u->id)->delete();
$rows = [
    [0, 'read', 2400], [0, 'radio', 900], [0, 'podcast', 600], [0, 'chapters', 5], [0, 'share_scripture', 2], [0, 'share_inspiration', 1], [0, 'open', 1],
    [1, 'read', 1500], [1, 'radio', 1200], [1, 'open', 1], [1, 'chapters', 3],
    [2, 'read', 1800], [2, 'podcast', 1500], [2, 'open', 1], [2, 'share_scripture', 1],
    [3, 'read', 900], [3, 'open', 1],
    [4, 'read', 2100], [4, 'radio', 600], [4, 'open', 1],
    [5, 'open', 1], [5, 'radio', 400],
];
foreach ($rows as [$ago, $kind, $n]) {
    $unit = in_array($kind, ['read', 'radio', 'podcast'], true) ? 'seconds' : 'count';
    MemberActivity::updateOrCreate(['user_id' => $u->id, 'day' => today()->subDays($ago)->toDateString(), 'kind' => $kind], [$unit => $n]);
}

// A church whose next payment is close, with its invoice issued.
$church = Church::where('slug', 'grace-lira')->first();
$church->invoices()->delete();
$church->update(['billing_status' => 'trial', 'trial_ends_at' => today()->addDays(5), 'paid_until' => null]);
app(BillingService::class)->generateDueInvoices();

$token = $u->createToken('docs')->plainTextToken;
file_put_contents(__DIR__.'/token.txt', $token);
echo "ready\n";
