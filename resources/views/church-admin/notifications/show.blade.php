<x-layouts.church-admin :church="$church" :title="$broadcast->title">
    <x-ff.page-header :title="$broadcast->title" :breadcrumbs="[__('Notifications') => route('church-admin.notifications.index', $church), Str::limit($broadcast->title, 30) => null]" />

    <div class="row g-4">
        <div class="col-lg-7">
            <x-ff.card>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <x-ff.status :status="$broadcast->status" />
                    @if ($broadcast->is_urgent)<span class="ff-badge ff-badge-danger"><i class="bi bi-exclamation-triangle"></i> {{ __('Urgent') }}</span>@endif
                    <span class="ff-badge">{{ $broadcast->audienceLabel() }}</span>
                    <span class="ff-badge">{{ __(config('faithfellows.notification_categories.'.$broadcast->category, $broadcast->category)) }}</span>
                </div>
                <div style="white-space: pre-line">{{ $broadcast->body }}</div>
                @if ($broadcast->url)<a href="{{ $broadcast->url }}" class="small d-inline-block mt-2" target="_blank" rel="noopener">{{ $broadcast->url }}</a>@endif
                <div class="small text-ff-muted mt-3">
                    {{ __('By :name', ['name' => $broadcast->sender?->name ?? '—']) }} ·
                    @if ($broadcast->sent_at){{ __('sent :d', ['d' => $broadcast->sent_at->format('j M Y, H:i')]) }}@elseif ($broadcast->scheduled_at){{ __('scheduled for :d', ['d' => $broadcast->scheduled_at->format('j M Y, H:i')]) }}@endif
                </div>
            </x-ff.card>
        </div>
        <div class="col-lg-5 d-grid gap-3 align-content-start">
            <div class="row g-3">
                <div class="col-6"><x-ff.stat-card :label="__('Recipients')" :value="number_format($broadcast->recipients_count)" icon="bi-people" primary /></div>
                <div class="col-6"><x-ff.stat-card :label="__('Opened in app')" :value="$reads && $reads->delivered ? round($reads->opened / $reads->delivered * 100).'%' : '—'" icon="bi-envelope-open">{{ $reads ? __(':o of :d', ['o' => number_format($reads->opened), 'd' => number_format($reads->delivered)]) : '' }}</x-ff.stat-card></div>
            </div>
            <x-ff.card :title="__('Delivery by channel')" flush>
                <div class="table-responsive">
                    <table class="table ff-table mb-0">
                        <thead><tr><th>{{ __('Channel') }}</th><th class="text-end">{{ __('Delivered') }}</th><th class="text-end">{{ __('Failed') }}</th><th class="text-end">{{ __('Cost') }}</th></tr></thead>
                        <tbody>
                            <tr><td>{{ __('In-app') }}</td><td class="text-end">{{ number_format($reads->delivered ?? 0) }}</td><td class="text-end">0</td><td class="text-end">—</td></tr>
                            @foreach ($stats as $channel => $s)
                                <tr><td>{{ __(config('faithfellows.notification_channels.'.$channel, $channel)) }}</td><td class="text-end">{{ number_format($s->sent) }}</td><td class="text-end @if ($s->failed) text-danger @endif">{{ number_format($s->failed) }}</td><td class="text-end">{{ $s->cost > 0 ? ff_money($s->cost) : '—' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ff.card>
            <p class="small text-ff-muted">{{ __('Messages to people in quiet hours are held until the morning, so numbers can grow for a few hours after sending.') }}</p>
        </div>
    </div>
</x-layouts.church-admin>
