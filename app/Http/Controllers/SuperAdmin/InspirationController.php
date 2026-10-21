<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Inspiration;
use App\Models\PlatformSetting;
use App\Services\InspirationBroadcaster;
use App\Services\InspirationService;
use App\Services\PushBroadcaster;
use App\Services\VerseBroadcaster;
use App\Support\Audit;
use App\Support\VerseOfTheDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The short inspiring messages the app's home screen shows in the morning, mid-morning and evening. The super admin adds, edits,
 * switches off and removes them; each part of the day cycles through its own messages, one a day.
 */
class InspirationController extends Controller
{
    public function index(Request $request, InspirationService $service, InspirationBroadcaster $push, VerseBroadcaster $verse): View
    {
        $slot = $request->query('slot');

        return view('super-admin.inspiration.index', [
            'push' => [
                'auto' => $push->auto(),
                'times' => $push->times(),
                'audience' => $push->audience(),
                'members' => app(PushBroadcaster::class)->members(),
                'last' => collect(array_keys(Inspiration::SLOTS))->mapWithKeys(fn ($s) => [$s => PlatformSetting::get("inspiration_last_{$s}")])->all(),
                'last_instant' => PlatformSetting::get('inspiration_last_instant'),
            ],
            'verse' => ['auto' => $verse->auto(), 'time' => $verse->time(), 'last' => PlatformSetting::get('verse_push_last'), 'today' => VerseOfTheDay::reference()],
            'messages' => Inspiration::query()->when(array_key_exists((string) $slot, Inspiration::SLOTS), fn ($q) => $q->where('slot', $slot))->orderBy('slot')->orderBy('id')->get(),
            'counts' => Inspiration::where('is_active', true)->selectRaw('slot, count(*) as n')->groupBy('slot')->pluck('n', 'slot'),
            'slot' => $slot,
            'now' => $service->current(),
        ]);
    }

    /** The scheduled sends: on or off, and the time of each part of the day. */
    public function schedule(Request $request, InspirationBroadcaster $push): RedirectResponse
    {
        $data = $request->validate([
            'times' => ['required', 'array'],
            'times.*' => ['required', 'date_format:H:i'],
        ]);
        $push->save($request->boolean('auto'), $data['times']);
        Audit::log('inspiration.schedule_saved', null, $request->boolean('auto') ? __('Scheduled sends on') : __('Scheduled sends off'));

        return back()->with('status', $request->boolean('auto') ? __('Saved. The messages go out by themselves at these times.') : __('Saved. Scheduled sending is off; you can still send a message by hand.'));
    }

    /** The verse of the day on phones: on or off, and the time it goes out each morning. */
    public function verseSchedule(Request $request, VerseBroadcaster $verse): RedirectResponse
    {
        $data = $request->validate(['time' => ['required', 'date_format:H:i']]);
        $verse->save($request->boolean('auto'), $data['time']);
        Audit::log('inspiration.verse_schedule_saved', null, $request->boolean('auto') ? __('Verse of the day on') : __('Verse of the day off'));

        return back()->with('status', $request->boolean('auto') ? __('Saved. The verse of the day goes to phones at :time every morning.', ['time' => $data['time']]) : __('Saved. The verse of the day is not sent to phones; you can still send it by hand.'));
    }

    /** Sends today’s verse to everyone's phones now (twice within five minutes is refused). */
    public function verseSend(VerseBroadcaster $verse): RedirectResponse
    {
        if (! Cache::add('verse-instant', 1, now()->addMinutes(5))) {
            return back()->with('status', __('The verse was just sent. Wait a few minutes before sending it again.'));
        }

        $people = $verse->sendNow();
        Audit::log('inspiration.verse_sent', null, __('Sent to :n phones', ['n' => $people]));

        return back()->with('status', $this->sentMessage($people, __('Today’s verse')));
    }

    /** Send one message to everyone's phones right now (twice within five minutes is refused, so a double tap does not buzz everyone twice). */
    public function send(Inspiration $inspiration, InspirationBroadcaster $push): RedirectResponse
    {
        if (! Cache::add('inspiration-instant:'.$inspiration->id, 1, now()->addMinutes(5))) {
            return back()->with('status', __('That message was just sent. Wait a few minutes before sending it again.'));
        }

        $people = $push->sendNow($inspiration);
        Audit::log('inspiration.sent', $inspiration, __('Sent to :n phones', ['n' => $people]));

        return back()->with('status', $this->sentMessage($people, __('The message')));
    }

    /**
     * What the super admin is told after a send: how many phones it went to (every member who has the app on a phone; no church is involved) and,
     * when it is fewer than all members, why: a message can only pop up on a phone that is set up for notifications.
     */
    protected function sentMessage(int $phones, string $what): string
    {
        $others = max(0, app(PushBroadcaster::class)->members() - $phones);
        $text = __(':what is on its way to :people who have the app on a phone (anyone who switched this kind off is skipped).', ['what' => $what, 'people' => trans_choice('{1} :n member|[0,*] :n members', $phones, ['n' => $phones])]);

        return $others > 0
            ? $text.' '.trans_choice('{1} :m other member has not|[2,*] :m other members have not', $others, ['m' => $others]).' '.__('turned notifications on in the app yet; they still see it on their Home screen, and the app now asks them to turn notifications on.')
            : $text;
    }

    public function store(Request $request): RedirectResponse
    {
        $message = Inspiration::create($this->validated($request) + ['is_active' => true]);
        Audit::log('inspiration.created', $message, __('Inspiration added'));

        return back()->with('status', __('Message added.'));
    }

    public function update(Request $request, Inspiration $inspiration): RedirectResponse
    {
        $inspiration->update($this->validated($request) + ['is_active' => $request->boolean('is_active')]);
        Audit::log('inspiration.updated', $inspiration, __('Inspiration changed'));

        return back()->with('status', __('Message saved.'));
    }

    public function destroy(Inspiration $inspiration): RedirectResponse
    {
        $inspiration->delete();
        Audit::log('inspiration.deleted', null, __('Inspiration removed'));

        return back()->with('status', __('Message removed.'));
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'slot' => ['required', Rule::in(array_keys(Inspiration::SLOTS))],
            'body' => ['required', 'string', 'max:280'],
            'reference' => ['nullable', 'string', 'max:80'],
        ]);
    }
}
