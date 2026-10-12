<x-layouts.church-admin :church="$church" :title="__('Audit log')">
    <x-ff.page-header :title="__('Audit log')" :subtitle="__('Every change made by your team and by FaithFellows support.')">
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="action" value="{{ request('action') }}" class="form-control form-control-sm" placeholder="{{ __('Filter by action, e.g. sermon') }}" aria-label="{{ __('Filter') }}">
        </form>
    </x-ff.page-header>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('When') }}</th><th>{{ __('Who') }}</th><th>{{ __('Action') }}</th><th>{{ __('Details') }}</th><th>{{ __('IP') }}</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="small text-nowrap">{{ $log->created_at->format('j M Y, H:i') }}</td>
                            <td class="small">
                                {{ $log->user?->name ?? __('System') }}
                                @if ($log->impersonator)<div class="small-xs text-warning"><i class="bi bi-incognito"></i> {{ __('via :name (support)', ['name' => $log->impersonator->name]) }}</div>@endif
                            </td>
                            <td><code class="text-ff-primary">{{ $log->action }}</code></td>
                            <td class="small">
                                {{ $log->description }}
                                @if ($log->new_values)
                                    <details class="small-xs text-ff-muted"><summary>{{ __('Changes') }}</summary>
                                        @foreach (collect($log->new_values)->take(12) as $field => $value)
                                            <div><strong>{{ $field }}</strong>: {{ Str::limit(is_scalar($value) ? (string) $value : json_encode($value), 80) }}</div>
                                        @endforeach
                                    </details>
                                @endif
                            </td>
                            <td class="small-xs text-ff-muted">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ff.empty-state icon="bi-clipboard-data" :title="__('No activity recorded yet')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())<div class="ff-table-footer">{{ $logs->links() }}</div>@endif
    </div>
</x-layouts.church-admin>
