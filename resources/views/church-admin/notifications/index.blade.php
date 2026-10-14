<x-layouts.church-admin :church="$church" :title="__('Notifications')">
    <x-ff.page-header :title="__('Notifications')" :subtitle="__('Messages sent to your members, and how they were delivered.')">
        <a href="{{ route('church-admin.notifications.create', $church) }}" class="btn btn-ff-primary"><i class="bi bi-send"></i> {{ __('New notification') }}</a>
    </x-ff.page-header>

    <div class="row g-3 mb-4">
        @foreach (['mail' => [__('Emails'), 'bi-envelope'], 'webpush' => [__('Push'), 'bi-phone-vibrate'], 'sms' => [__('SMS'), 'bi-chat-left-text'], 'whatsapp' => [__('WhatsApp'), 'bi-whatsapp']] as $channel => [$label, $icon])
            @php($m = $month[$channel] ?? null)
            <div class="col-6 col-lg-3">
                <x-ff.stat-card :label="$label.' · '.__('this month')" :value="number_format($m->sent ?? 0)" :icon="$icon">
                    @if ($m && $m->failed){{ __(':n failed', ['n' => $m->failed]) }}@endif
                    @if ($channel === 'sms' && $m && $m->cost > 0) · {{ ff_money($m->cost) }}@endif
                </x-ff.stat-card>
            </div>
        @endforeach
    </div>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Message') }}</th><th>{{ __('Audience') }}</th><th>{{ __('Channels') }}</th><th class="text-end">{{ __('Recipients') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($broadcasts as $b)
                        <tr>
                            <td>
                                <a href="{{ route('church-admin.notifications.show', [$church, $b]) }}" class="fw-semibold text-ff-black">@if ($b->is_urgent)<i class="bi bi-exclamation-triangle-fill text-danger"></i> @endif{{ $b->title }}</a>
                                <div class="small-xs text-ff-muted">{{ ($b->sent_at ?? $b->scheduled_at ?? $b->created_at)->format('j M Y, H:i') }} · {{ $b->sender?->name }}</div>
                            </td>
                            <td class="small">{{ $b->audienceLabel() }}</td>
                            <td class="small">{{ collect(['in-app'])->merge($b->channels ?? [])->implode(', ') }}</td>
                            <td class="text-end">{{ $b->status === 'sent' ? number_format($b->recipients_count) : '—' }}</td>
                            <td><x-ff.status :status="$b->status" /></td>
                            <td class="text-end">
                                @if ($b->status === 'scheduled')
                                    <form method="POST" action="{{ route('church-admin.notifications.destroy', [$church, $b]) }}" data-ff-confirm="{{ __('Cancel this scheduled message?') }}">@csrf @method('DELETE')
                                        <button class="btn btn-sm btn-ff-ghost text-danger">{{ __('Cancel') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ff.empty-state icon="bi-send" :title="__('No notifications sent yet')" :message="__('Reach your whole church in seconds — in the app, by email, push or SMS.')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($broadcasts->hasPages())<div class="ff-table-footer">{{ $broadcasts->links() }}</div>@endif
    </div>
</x-layouts.church-admin>
