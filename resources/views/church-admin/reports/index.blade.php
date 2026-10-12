<x-layouts.church-admin :church="$church" :title="__('Reports')">
    <x-ff.page-header :title="__('Reported content')" :subtitle="__('Members flag posts, comments, prayers and testimonies that break your community guidelines.')" />

    <nav class="ff-tabs mb-4" aria-label="{{ __('Status') }}">
        @foreach (['open' => __('Open'), 'actioned' => __('Hidden'), 'dismissed' => __('Dismissed')] as $value => $label)
            <a href="{{ route('church-admin.reports.index', [$church, 'status' => $value]) }}" @class(['ff-tab', 'active' => $status === $value])>{{ $label }}</a>
        @endforeach
    </nav>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Content') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Reported by') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($reports as $report)
                        <tr>
                            <td style="max-width: 460px">@include('partials.reported-content')</td>
                            <td class="small"><strong>{{ __(\App\Models\ContentReport::REASONS[$report->reason]) }}</strong>@if ($report->details)<div class="text-ff-muted">{{ $report->details }}</div>@endif</td>
                            <td class="small">{{ $report->reporter?->name }}<div class="small-xs text-ff-muted">{{ $report->created_at->diffForHumans() }}</div></td>
                            <td class="text-end text-nowrap">
                                @if ($report->status === 'open')
                                    <form method="POST" action="{{ route('church-admin.reports.update', [$church, $report]) }}" class="d-inline">@csrf @method('PUT')
                                        <button name="action" value="hide" class="btn btn-sm btn-ff-primary">{{ __('Hide') }}</button>
                                        <button name="action" value="dismiss" class="btn btn-sm btn-ff-ghost">{{ __('Dismiss') }}</button>
                                    </form>
                                @else
                                    <span class="small text-ff-muted">{{ $report->handler?->name }} · {{ $report->handled_at?->diffForHumans() }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-ff.empty-state icon="bi-flag" :title="__('Nothing reported')" :message="__('Your community is behaving beautifully.')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($reports->hasPages())<div class="ff-table-footer">{{ $reports->links() }}</div>@endif
    </div>
</x-layouts.church-admin>
