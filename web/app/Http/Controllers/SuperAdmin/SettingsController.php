<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\Messaging\AfricasTalking;
use App\Services\Messaging\WebPushSender;
use App\Services\PaymentService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Platform settings and payment gateway health.
 *
 * Secrets (API keys) stay in the server's .env — this screen shows whether each
 * integration is configured, lets staff switch gateways off during outages and
 * edits non-secret platform settings.
 */
class SettingsController extends Controller
{
    /** Editable platform settings: key => [label, type, help]. */
    public const SETTINGS = [
        'global_announcement' => ['Global announcement banner', 'text', 'Shown at the top of every page. Leave empty to hide.'],
        'registrations_open' => ['Accept new church applications', 'bool', 'Turn off to pause the “Register your church” form.'],
        'support_email' => ['Support email', 'email', 'Shown to churches on the support page.'],
        'support_phone' => ['Support phone', 'text', null],
        'trial_months' => ['Free trial length (months)', 'number', 'Applies to newly approved churches.'],
        'opening_flash_enabled' => ['Opening photos: show when the app opens', 'bool', 'A flash of rolling photos each time the app is opened. Turn off to open the app straight away.'],
        'opening_flash_seconds' => ['Opening photos: seconds shown', 'number', 'How long the photos stay on screen before the app opens, from 2 to 20 seconds. Default 6. Phones pick up a change the next time the app is opened.'],
        'opening_flash_speed' => ['Opening photos: rolling speed', 'number', 'How fast the photos roll, from 1 (slow) to 10 (fast). Default 5.'],
        'opening_flash_title' => ['Opening photos: title', 'text', 'The big line under the photos. Leave empty for “Stay Connected in Faith”.'],
        'opening_flash_text' => ['Opening photos: sentence under the title', 'text', 'Leave empty for the standard sentence about worshipping and growing together.'],
        'quiz_question_seconds' => ['Quiz games: seconds to answer each question', 'number', 'How long every question stays open in a game, from 5 to 120 seconds. Default 15. It applies from the next question of every game, including one being played.'],
        'quiz_reveal_seconds' => ['Quiz games: seconds the answer shows', 'number', 'How long the right answer and the scores stay on screen before the next question, from 2 to 30 seconds. Default 5.'],
        'android_app_url' => ['App download link', 'url', 'The “Download the app” buttons on the website open this link: a Play Store link, or the link of the latest Android build.'],
        'ios_app_url' => ['iPhone download link', 'url', 'The App Store link, or the TestFlight invitation link while iPhone testers are being invited. Church join links send iPhone users here. Leave empty until there is one.'],
        'maintenance_note' => ['Maintenance notice for admins', 'text', 'Shown on church admin dashboards only.'],
        'dormancy_warn_days' => ['Warn a quiet church after (days)', 'number', 'No staff sign-in, nothing published and no new followers for this long: the church gets a warning. Default 30.'],
        'dormancy_block_days' => ['Block it after the warning (days)', 'number', 'If it is still quiet this long after the warning, the church is blocked. Default 14.'],
    ];

    public function edit(AfricasTalking $sms, WebPushSender $push): View
    {
        return view('super-admin.settings.index', [
            'values' => collect(self::SETTINGS)->mapWithKeys(fn ($def, $key) => [$key => PlatformSetting::get($key, $this->defaults()[$key] ?? null)]),
            'integrations' => [
                ['name' => __('Email (SMTP)'), 'ok' => ! in_array(config('mail.default'), ['log', 'array'], true), 'detail' => config('mail.default').' · '.config('mail.from.address')],
                ['name' => __('SMS (Africa’s Talking)'), 'ok' => $sms->smsEnabled(), 'detail' => $sms->smsEnabled() ? config('services.africastalking.username') : __('Test mode — messages are logged')],
                ['name' => __('WhatsApp'), 'ok' => (bool) config('services.africastalking.whatsapp_enabled'), 'detail' => config('services.africastalking.whatsapp_number') ?: __('Not configured')],
                ['name' => __('Web push (VAPID)'), 'ok' => $push->configured(), 'detail' => $push->configured() ? __('Keys set') : __('Run php artisan ff:vapid')],
                ['name' => __('Real-time (broadcasting)'), 'ok' => ! in_array(config('broadcasting.default'), ['log', 'null'], true), 'detail' => config('broadcasting.default')],
                ['name' => __('Queue worker'), 'ok' => config('queue.default') !== 'sync', 'detail' => config('queue.default').' · '.__(':n failed jobs', ['n' => DB::table('failed_jobs')->count()])],
                ['name' => __('Live streaming'), 'ok' => filled(config('services.streaming.rtmp_url')), 'detail' => config('services.streaming.provider')],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'global_announcement' => ['nullable', 'string', 'max:300'],
            'registrations_open' => ['boolean'],
            'support_email' => ['nullable', 'email'],
            'support_phone' => ['nullable', 'string', 'max:40'],
            'trial_months' => ['nullable', 'integer', 'min:0', 'max:24'],
            'opening_flash_enabled' => ['boolean'],
            'opening_flash_seconds' => ['nullable', 'integer', 'min:2', 'max:20'],
            'opening_flash_speed' => ['nullable', 'integer', 'min:1', 'max:10'],
            'opening_flash_title' => ['nullable', 'string', 'max:60'],
            'opening_flash_text' => ['nullable', 'string', 'max:160'],
            'quiz_question_seconds' => ['nullable', 'integer', 'min:5', 'max:120'],
            'quiz_reveal_seconds' => ['nullable', 'integer', 'min:2', 'max:30'],
            'android_app_url' => ['nullable', 'url:https,http', 'max:500'],
            'ios_app_url' => ['nullable', 'url:https,http', 'max:500'],
            'maintenance_note' => ['nullable', 'string', 'max:300'],
            'dormancy_warn_days' => ['nullable', 'integer', 'min:7', 'max:365'],
            'dormancy_block_days' => ['nullable', 'integer', 'min:3', 'max:180'],
        ]);

        foreach (array_keys(self::SETTINGS) as $key) {
            PlatformSetting::put($key, self::SETTINGS[$key][1] === 'bool' ? ($request->boolean($key) ? '1' : '0') : ($data[$key] ?? null));
        }
        Audit::log('platform.settings_updated', null, null, [], array_intersect_key($data, self::SETTINGS));

        return back()->with('success', __('Settings saved.'));
    }

    public function gateways(): View
    {
        $since = now()->subDays(30);
        $stats = DB::table('donations')->where('created_at', '>=', $since)
            ->selectRaw("method, COUNT(*) total, SUM(status = 'succeeded') ok, SUM(status = 'failed') failed, SUM(CASE WHEN status = 'succeeded' THEN amount ELSE 0 END) volume, MAX(paid_at) last_paid")
            ->groupBy('method')->get()->keyBy('method');

        $gateways = collect(['mtn_momo' => 'MTN MoMo', 'airtel_money' => 'Airtel Money', 'iotec' => 'Iotec', 'stripe' => 'Stripe (cards)', 'ussd' => 'USSD'])
            ->map(fn ($label, $method) => [
                'method' => $method,
                'label' => $label,
                'enabled' => PaymentService::enabled($method),
                'sandbox' => PaymentService::isSandbox($method),
                'stats' => $stats[$method] ?? null,
                'webhook' => match ($method) {
                    'mtn_momo' => route('webhooks.payments', 'mtn-momo'),
                    'airtel_money' => route('webhooks.payments', 'airtel'),
                    'ussd' => route('webhooks.ussd').(config('services.africastalking.ussd_secret') ? '?secret=…' : ''),
                    default => route('webhooks.payments', $method),
                },
            ]);

        return view('super-admin.settings.gateways', [
            'gateways' => $gateways,
            'sandboxForced' => (bool) config('faithfellows.payments.sandbox'),
            'pending' => DB::table('donations')->whereIn('status', ['pending', 'processing'])->where('created_at', '<', now()->subMinutes(30))->count(),
        ]);
    }

    public function toggleGateway(Request $request, string $method): RedirectResponse
    {
        abort_unless(in_array($method, PaymentService::methods(), true) && ! in_array($method, ['cash', 'bank'], true), 404);
        $enabled = $request->boolean('enabled');
        PlatformSetting::put("gateway.{$method}.enabled", $enabled ? '1' : '0');
        PaymentService::flush();
        Audit::log('platform.gateway_'.($enabled ? 'enabled' : 'disabled'), null, $method);

        return back()->with('success', $enabled ? __(':m switched on.', ['m' => $method]) : __(':m switched off for all churches.', ['m' => $method]));
    }

    /**
     * @return array<string, string>
     */
    protected function defaults(): array
    {
        return [
            'registrations_open' => '1',
            'opening_flash_enabled' => '1',
            'opening_flash_seconds' => '6',
            'opening_flash_speed' => '5',
            'support_email' => config('faithfellows.company.email'),
            'support_phone' => config('faithfellows.company.phone'),
            'trial_months' => '12',
            'quiz_question_seconds' => (string) config('faithfellows.quizzes.question_seconds'),
            'quiz_reveal_seconds' => (string) config('faithfellows.quizzes.reveal_seconds'),
            'android_app_url' => (string) config('faithfellows.app.android_url'),
            'ios_app_url' => (string) config('faithfellows.app.ios_url'),
            'dormancy_warn_days' => (string) config('faithfellows.dormancy.warn_days'),
            'dormancy_block_days' => (string) config('faithfellows.dormancy.block_days'),
        ];
    }
}
