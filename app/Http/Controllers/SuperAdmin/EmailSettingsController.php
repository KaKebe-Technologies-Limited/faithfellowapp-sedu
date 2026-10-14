<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\MailSettings;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The email account the platform sends from (a Gmail account with an App Password): sign-in codes, receipts and the thank-you
 * email every giver gets. The password is saved encrypted and is never shown again.
 */
class EmailSettingsController extends Controller
{
    public function edit(): View
    {
        return view('super-admin.email.edit', [
            'v' => MailSettings::values(),
            'active' => MailSettings::active(),
            'driver' => config('mail.default'),
            'sendingFrom' => config('mail.from.address'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'host' => ['required', 'string', 'max:120'],
            'port' => ['required', 'integer', 'in:25,465,587,2525'],
            'username' => ['required', 'string', 'max:160'],
            'password' => ['nullable', 'string', 'max:120'],
            'from_address' => ['required', 'email', 'max:160'],
            'from_name' => ['required', 'string', 'max:80'],
        ]);
        $data['enabled'] = $request->boolean('enabled');

        MailSettings::save($data);
        Audit::log('platform.email_settings_updated', null, $data['from_address'], [], ['enabled' => $data['enabled'], 'host' => $data['host'], 'port' => $data['port'], 'username' => $data['username'], 'password_changed' => filled($data['password'] ?? null)]);

        return back()->with('success', $data['enabled'] ? __('Email settings saved. Send a test email to check them.') : __('Email settings saved, but sending from this account is switched off.'));
    }

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email', 'max:160']]);

        if (! MailSettings::active() && in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->withInput()->withErrors(['to' => __('Switch on the account above and save it first. Right now emails are only written to a log file.')]);
        }

        $problem = MailSettings::sendTest($data['to']);
        Audit::log('platform.email_test', null, $data['to'], [], ['ok' => $problem === null]);

        return $problem
            ? back()->withInput()->withErrors(['to' => $problem])
            : back()->with('success', __('Test email sent to :to. Check the inbox (and the spam folder).', ['to' => $data['to']]));
    }
}
