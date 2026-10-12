<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Models\ChurchBroadcast;
use App\Notifications\Church\ChurchMessage;
use App\Services\Messaging\AfricasTalking;
use App\Services\Notifications\BroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Notification composer: write once, deliver in-app, by email, push, SMS
 * or WhatsApp to the whole church or a segment — now or scheduled.
 */
class NotificationController extends Controller
{
    public function __construct(protected BroadcastService $broadcasts) {}

    public function index(Church $church): View
    {
        return view('church-admin.notifications.index', [
            'church' => $church,
            'broadcasts' => $church->churchBroadcasts()->with('sender')->latest()->paginate(20),
            'month' => \DB::table('notification_deliveries')->where('church_id', $church->id)->where('created_at', '>=', now()->startOfMonth())
                ->selectRaw("channel, SUM(status = 'sent') sent, SUM(status = 'failed') failed, SUM(cost) cost")->groupBy('channel')->get()->keyBy('channel'),
        ]);
    }

    public function create(Request $request, Church $church): View
    {
        return view('church-admin.notifications.create', [
            'church' => $church,
            'branches' => $church->branches()->orderBy('name')->pluck('name', 'id'),
            'groups' => $church->smallGroups()->orderBy('name')->pluck('name', 'id'),
            'estimate' => $this->broadcasts->estimate($church->id, 'members', []),
            'smsEnabled' => app(AfricasTalking::class)->smsEnabled(),
            'smsCost' => (float) config('services.africastalking.sms_cost', 35),
        ]);
    }

    public function estimate(Request $request, Church $church): JsonResponse
    {
        [$audience, $options] = $this->audience($request, $church);
        $segments = max(1, (int) ceil(mb_strlen($church->name.': '.$request->query('title', '').' — '.$request->query('body', '')) / 153));

        return response()->json($this->broadcasts->estimate($church->id, $audience, $options, min($segments, 2)));
    }

    public function store(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:2000'],
            'url' => ['nullable', 'url', 'max:500'],
            'category' => ['required', Rule::in(array_keys(config('faithfellows.notification_categories')))],
            'channels' => ['array'],
            'channels.*' => [Rule::in(['mail', 'webpush', 'sms', 'whatsapp'])],
            'is_urgent' => ['boolean'],
            'send' => ['required', Rule::in(['now', 'later'])],
            'scheduled_at' => ['nullable', 'required_if:send,later', 'date', 'after:now'],
        ]);
        [$audience, $options] = $this->audience($request, $church);

        $broadcast = $church->churchBroadcasts()->create([
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'body' => strip_tags($data['body']),
            'url' => $data['url'] ?? null,
            'audience' => $audience,
            'audience_options' => $options ?: null,
            'channels' => array_values($data['channels'] ?? []),
            'category' => $data['category'],
            'is_urgent' => $request->boolean('is_urgent'),
            'status' => $data['send'] === 'later' ? 'scheduled' : 'draft',
            'scheduled_at' => $data['send'] === 'later' ? Carbon::parse($data['scheduled_at']) : null,
        ]);

        if ($data['send'] === 'now') {
            $this->broadcasts->send($broadcast);

            return redirect()->route('church-admin.notifications.show', [$church, $broadcast])
                ->with('success', trans_choice('Sending to :count person.|Sending to :count people.', $broadcast->fresh()->recipients_count));
        }

        return redirect()->route('church-admin.notifications.index', $church)
            ->with('success', __('Scheduled for :when.', ['when' => $broadcast->scheduled_at->format('D j M, g:i A')]));
    }

    public function show(Church $church, ChurchBroadcast $churchBroadcast): View
    {
        return view('church-admin.notifications.show', [
            'church' => $church,
            'broadcast' => $churchBroadcast->load('sender'),
            'stats' => $this->broadcasts->stats($churchBroadcast),
            'reads' => \DB::table('notifications')->where('church_id', $church->id)
                ->where('type', ChurchMessage::class)
                ->where('data->broadcast_id', $churchBroadcast->id)
                ->selectRaw('COUNT(*) delivered, COUNT(read_at) opened')->first(),
        ]);
    }

    public function destroy(Church $church, ChurchBroadcast $churchBroadcast): RedirectResponse
    {
        abort_unless($churchBroadcast->status === 'scheduled', 422, __('Only scheduled messages can be cancelled.'));
        $churchBroadcast->delete();

        return back()->with('success', __('Scheduled message cancelled.'));
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    protected function audience(Request $request, Church $church): array
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(array_keys(ChurchBroadcast::AUDIENCES))],
            'branch_id' => ['nullable', 'required_if:audience,branch', Rule::exists('branches', 'id')->where('church_id', $church->id)],
            'small_group_id' => ['nullable', 'required_if:audience,group', Rule::exists('small_groups', 'id')->where('church_id', $church->id)],
            'stages' => ['array', 'required_if:audience,stage'],
            'stages.*' => [Rule::in(['visitor', 'new_member', 'member', 'worker', 'leader'])],
        ]);

        return [$data['audience'], array_filter([
            'branch_id' => $data['audience'] === 'branch' ? (int) $data['branch_id'] : null,
            'small_group_id' => $data['audience'] === 'group' ? (int) $data['small_group_id'] : null,
            'stages' => $data['audience'] === 'stage' ? array_values($data['stages'] ?? []) : null,
        ])];
    }
}
