<x-layouts.super-admin :title="__('Moderation')">
    <x-ff.page-header :title="__('Moderation')" :subtitle="__('Reports from members across all churches. Churches handle their own first — step in when a report goes stale or abuse repeats.')" />

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Open reports')" :value="number_format($counts['open'] ?? 0)" icon="bi-flag" primary /></div>
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Older than 48 h')" :value="number_format($stale)" icon="bi-hourglass-split" :href="route('super-admin.moderation.index', ['stale' => 1])" /></div>
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Hidden')" :value="number_format($counts['actioned'] ?? 0)" icon="bi-eye-slash" /></div>
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Dismissed')" :value="number_format($counts['dismissed'] ?? 0)" icon="bi-check2" /></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-9">
            <nav class="ff-tabs mb-3" aria-label="{{ __('Status') }}">
                @foreach (['open' => __('Open'), 'actioned' => __('Hidden'), 'dismissed' => __('Dismissed')] as $value => $label)
                    <a href="{{ route('super-admin.moderation.index', ['status' => $value]) }}" @class(['ff-tab', 'active' => $status === $value])>{{ $label }}</a>
                @endforeach
            </nav>
            <div class="ff-table-wrap">
                <div class="table-responsive">
                    <table class="table ff-table">
                        <thead><tr><th>{{ __('Content') }}</th><th>{{ __('Church') }}</th><th>{{ __('Reason') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($reports as $report)
                                <tr>
                                    <td style="max-width: 420px">@include('partials.reported-content')</td>
                                    <td class="small">{{ $report->church?->name ?? '—' }}<div class="small-xs text-ff-muted">{{ $report->created_at->diffForHumans() }}</div></td>
                                    <td class="small"><strong>{{ __(\App\Models\ContentReport::REASONS[$report->reason]) }}</strong><div class="text-ff-muted">{{ __('by :name', ['name' => $report->reporter?->name]) }}</div>@if ($report->details)<div>{{ $report->details }}</div>@endif</td>
                                    <td class="text-end text-nowrap">
                                        @if ($report->status === 'open')
                                            <form method="POST" action="{{ route('super-admin.moderation.update', $report) }}" class="d-inline">@csrf @method('PATCH')
                                                <button name="action" value="hide" class="btn btn-sm btn-ff-primary">{{ __('Hide') }}</button>
                                                <button name="action" value="dismiss" class="btn btn-sm btn-ff-ghost">{{ __('Dismiss') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('super-admin.moderation.update', $report) }}" class="d-inline" data-ff-confirm="{{ __('Suspend the author’s account and hide this content?') }}">@csrf @method('PATCH')
                                                <button name="action" value="suspend" class="btn btn-sm btn-ff-ghost text-danger" title="{{ __('Suspend author') }}"><i class="bi bi-person-x"></i></button>
                                            </form>
                                        @else
                                            <span class="small text-ff-muted">{{ $report->handler?->name }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><x-ff.empty-state icon="bi-shield-check" :title="__('Nothing to review')" class="border-0" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($reports->hasPages())<div class="ff-table-footer">{{ $reports->links() }}</div>@endif
            </div>
        </div>
        <aside class="col-lg-3">
            <x-ff.card :title="__('Repeat offenders')" :subtitle="__('2+ comments hidden after reports')">
                @forelse ($offenders as $o)
                    <a href="{{ route('super-admin.users.show', $o->id) }}" class="d-flex justify-content-between small py-1">
                        <span>{{ $o->name }} @if ($o->status === 'suspended')<span class="ff-badge ff-badge-danger">{{ __('Suspended') }}</span>@endif</span><strong>{{ $o->n }}</strong>
                    </a>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('None.') }}</p>
                @endforelse
            </x-ff.card>
        </aside>
    </div>
</x-layouts.super-admin>
