<?php

namespace App\Http\Controllers\ChurchAdmin;

use App\Http\Controllers\Controller;
use App\Models\Church;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Settings → Giving channels: which payment methods members can use and the
 * church account details shown for bank transfers / manual payments.
 */
class GivingChannelController extends Controller
{
    public const TYPES = ['mtn_momo', 'airtel_money', 'iotec', 'stripe', 'bank', 'ussd', 'cash'];

    public function edit(Church $church): View
    {
        $existing = $church->givingChannels()->get()->keyBy('type');

        return view('church-admin.settings.giving', [
            'church' => $church,
            'channels' => collect(self::TYPES)->mapWithKeys(fn ($type) => [$type => $existing[$type] ?? null]),
            'sandbox' => collect(self::TYPES)->mapWithKeys(fn ($type) => [$type => in_array($type, ['bank', 'cash'], true) ? false : PaymentService::isSandbox($type)]),
        ]);
    }

    public function update(Request $request, Church $church): RedirectResponse
    {
        $data = $request->validate([
            'channels' => ['array'],
            'channels.*.is_active' => ['boolean'],
            'channels.*.label' => ['nullable', 'string', 'max:120'],
            'channels.*.instructions' => ['nullable', 'string', 'max:1000'],
            'channels.*.details' => ['nullable', 'array'],
            'channels.*.details.*' => ['nullable', 'string', 'max:190'],
        ]);

        foreach (self::TYPES as $sort => $type) {
            $input = $data['channels'][$type] ?? [];
            $church->givingChannels()->updateOrCreate(['type' => $type], [
                'is_active' => (bool) ($input['is_active'] ?? false),
                'label' => $input['label'] ?? null,
                'instructions' => $input['instructions'] ?? null,
                'details' => array_filter($input['details'] ?? []),
                'sort' => $sort,
            ]);
        }

        if ($request->boolean('wizard')) {
            return redirect()->route('church-admin.setup', [$church, 'step' => 'team'])->with('success', __('Giving channels saved.'));
        }

        return back()->with('success', __('Giving channels saved.'));
    }
}
