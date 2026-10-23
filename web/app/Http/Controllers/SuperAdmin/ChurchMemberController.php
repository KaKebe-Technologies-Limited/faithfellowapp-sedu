<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * The platform admin puts registered users into a church, so they follow it without having to find and follow it themselves: from the church's page
 * (a list of emails or phone numbers, or people found by name and ticked) or from the user's page (pick the church). Also takes them out again.
 */
class ChurchMemberController extends Controller
{
    private const MAX_PER_REQUEST = 200;

    public function __construct(protected SubscriptionService $subscriptions) {}

    /** Adds the people listed (emails or phone numbers, one per line or separated by commas) and the ones ticked to this church. */
    public function assign(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate([
            'identifiers' => ['nullable', 'string', 'max:20000'],
            'user_ids' => ['nullable', 'array', 'max:'.self::MAX_PER_REQUEST],
            'user_ids.*' => ['integer'],
            'stage' => ['nullable', Rule::in(array_keys(Subscription::STAGES))],
            'notify' => ['boolean'],
        ]);
        // One per line or separated by commas or semicolons; a phone number may have spaces in it, so only email addresses are also split at spaces.
        $words = collect(preg_split('/[\r\n,;]+/', (string) ($data['identifiers'] ?? ''), -1, PREG_SPLIT_NO_EMPTY))
            ->flatMap(fn ($piece) => str_contains($piece, '@') ? preg_split('/\s+/', trim($piece), -1, PREG_SPLIT_NO_EMPTY) : [trim($piece)])->filter()->unique()->values();
        abort_if($words->count() > self::MAX_PER_REQUEST, 422, __('Add at most :n people at a time.', ['n' => self::MAX_PER_REQUEST]));

        $unknown = collect();
        $users = collect();
        foreach ($words as $word) {
            $found = $this->find($word);
            $found ? $users->put($found->id, $found) : $unknown->push($word);
        }
        User::whereIn('id', $data['user_ids'] ?? [])->get()->each(fn (User $u) => $users->put($u->id, $u));

        if ($users->isEmpty() && $unknown->isEmpty()) {
            return back()->withErrors(['identifiers' => __('Enter at least one email address or phone number, or tick the people to add.')]);
        }

        return back()->with('success', $this->report($this->run($users, $church, $request, $data), $unknown));
    }

    /** From the Users page (or a user's page): put this user into the church, or the churches, ticked. */
    public function assignUser(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'church_ids' => ['nullable', 'array', 'max:20'],
            'church_ids.*' => ['integer', 'exists:churches,id'],
            'church_id' => ['nullable', 'integer', 'exists:churches,id'],
            'stage' => ['nullable', Rule::in(array_keys(Subscription::STAGES))],
            'notify' => ['boolean'],
        ]);
        $ids = collect($data['church_ids'] ?? [])->when(! empty($data['church_id']), fn ($c) => $c->push($data['church_id']))->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return back()->withErrors(['church_ids' => __('Tick at least one church.')]);
        }

        $by = [];
        foreach (Church::whereIn('id', $ids)->orderBy('name')->get() as $church) {
            $by[$this->subscriptions->assign($user, $church, $request->user(), $data['stage'] ?? 'member', $request->boolean('notify'))][] = $church->name;
        }

        return back()->with('success', $this->reportUser($user, $by));
    }

    /** Takes the user out of the church: they stop following it (and can follow it again). */
    public function remove(Church $church, User $user): RedirectResponse
    {
        $this->subscriptions->unsubscribe($user, $church);
        Audit::log('member.removed_by_platform', null, __(':user removed from :church', ['user' => $user->name, 'church' => $church->name]), [], ['church_id' => $church->id, 'user_id' => $user->id], $church->id);

        return back()->with('success', __(':user no longer follows :church.', ['user' => $user->name, 'church' => $church->name]));
    }

    /** @return array<string, array<int, string>> the people by what happened to them */
    protected function run(Collection $users, Church $church, Request $request, array $data): array
    {
        $by = [];
        foreach ($users as $user) {
            $by[$this->subscriptions->assign($user, $church, $request->user(), $data['stage'] ?? 'member', $request->boolean('notify'))][] = $user->name;
        }

        return $by + ['church' => $church->name];
    }

    /** @param  array<string, mixed>  $by */
    protected function report(array $by, Collection $unknown): string
    {
        $church = $by['church'];
        $parts = [];
        if (! empty($by['added'])) {
            $parts[] = trans_choice(':n person now follows :church.|:n people now follow :church.', count($by['added']), ['n' => count($by['added']), 'church' => $church]);
        }
        if (! empty($by['already'])) {
            $parts[] = __(':n already followed it.', ['n' => count($by['already'])]);
        }
        if (! empty($by['blocked'])) {
            $parts[] = __('Blocked by the church (unblock them first): :names.', ['names' => implode(', ', $by['blocked'])]);
        }
        if (! empty($by['user_inactive'])) {
            $parts[] = __('Suspended accounts were skipped: :names.', ['names' => implode(', ', $by['user_inactive'])]);
        }
        if (! empty($by['church_inactive'])) {
            $parts[] = __('The church is not active, so nobody was added.');
        }
        if ($unknown->isNotEmpty()) {
            $parts[] = __('Not registered yet: :list.', ['list' => $unknown->take(15)->implode(', ').($unknown->count() > 15 ? '…' : '')]);
        }

        return implode(' ', $parts) ?: __('Nothing to do.');
    }

    /** @param  array<string, array<int, string>>  $by  the churches by what happened */
    protected function reportUser(User $user, array $by): string
    {
        $parts = [];
        if (! empty($by['added'])) {
            $parts[] = __(':user now follows :churches.', ['user' => $user->name, 'churches' => $this->names($by['added'])]);
        }
        if (! empty($by['already'])) {
            $parts[] = __('Already followed: :churches.', ['churches' => $this->names($by['already'])]);
        }
        if (! empty($by['blocked'])) {
            $parts[] = __('Blocked by :churches (unblock them there first).', ['churches' => $this->names($by['blocked'])]);
        }
        if (! empty($by['church_inactive'])) {
            $parts[] = __(':churches is not active.', ['churches' => $this->names($by['church_inactive'])]);
        }
        if (! empty($by['user_inactive'])) {
            $parts[] = __('This account is suspended, so it cannot be added to a church.');
        }

        return implode(' ', $parts) ?: __('Nothing to do.');
    }

    /** "A", "A and B", "A, B and C" */
    protected function names(array $names): string
    {
        return count($names) > 1 ? implode(', ', array_slice($names, 0, -1)).' '.__('and').' '.end($names) : (string) ($names[0] ?? '');
    }

    /** A registered user by email address or phone number (the last nine digits, whatever the format they were saved in). */
    protected function find(string $word): ?User
    {
        if (str_contains($word, '@')) {
            return User::where('email', mb_strtolower($word))->first();
        }
        $digits = preg_replace('/\D/', '', $word);

        // The number may have been saved with spaces, dashes, brackets or a plus sign.
        return strlen($digits) >= 9 ? User::whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') like ?", ['%'.substr($digits, -9)])->first() : null;
    }
}
