<x-layouts.super-admin :title="__('Audit logs')">
    <x-ff.page-header :title="__('Audit logs')" :subtitle="__('Every admin action on the platform, including actions taken while impersonating.')">
        <a href="{{ route('super-admin.audit-logs.export', request()->query()) }}" class="btn btn-ff-soft"><i class="bi bi-download"></i> {{ __('Export CSV') }}</a>
    </x-ff.page-header>

    <form method="GET" class="ff-card ff-card-sm row g-2 align-items-end mb-3">
        <div class="col-md-3">
            <label class="form-label small" for="f-church">{{ __('Church') }}</label>
            <select name="church" id="f-church" class="form-select form-select-sm">
                <option value="">{{ __('All churches') }}</option>
                @foreach ($churches as $id => $name)<option value="{{ $id }}" @selected((string) request('church') === (string) $id)>{{ $name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small" for="f-action">{{ __('Action') }}</label>
            <select name="action" id="f-action" class="form-select form-select-sm">
                <option value="">{{ __('Any action') }}</option>
                @foreach ($actions as $a)<option value="{{ $a }}" @selected(request('action') === $a)>{{ $a }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-2"><label class="form-label small" for="f-q">{{ __('User') }}</label><input type="search" name="q" id="f-q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('name or email') }}"></div>
        <div class="col-md-2"><label class="form-label small" for="f-from">{{ __('From') }}</label><input type="date" name="from" id="f-from" value="{{ request('from') }}" class="form-control form-control-sm"></div>
        <div class="col-md-2 d-flex gap-2">
            <div class="flex-grow-1"><label class="form-label small" for="f-to">{{ __('To') }}</label><input type="date" name="to" id="f-to" value="{{ request('to') }}" class="form-control form-control-sm"></div>
            <button class="btn btn-sm btn-ff-primary align-self-end" aria-label="{{ __('Filter') }}"><i class="bi bi-funnel"></i></button>
        </div>
        <div class="col-12"><x-ff.checkbox name="platform" :label="__('Platform-level actions only')" :checked="request('platform')" onchange="this.form.submit()" /></div>
    </form>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('When') }}</th><th>{{ __('Who') }}</th><th>{{ __('Action') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Church') }}</th><th>{{ __('IP') }}</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="small text-nowrap">{{ $log->created_at?->format('j M Y, H:i') }}</td>
                            <td class="small">{{ $log->user?->name ?? __('System') }}@if ($log->impersonator)<div class="small-xs text-danger"><i class="bi bi-person-badge"></i> {{ __('as :name', ['name' => $log->impersonator->name]) }}</div>@endif</td>
                            <td><code class="small">{{ $log->action }}</code>@if ($log->description)<div class="small-xs text-ff-muted">{{ Str::limit($log->description, 80) }}</div>@endif</td>
                            <td class="small">{{ $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : '—' }}</td>
                            <td class="small">{{ $log->church_id ? ($churches[$log->church_id] ?? '#'.$log->church_id) : __('Platform') }}</td>
                            <td class="small text-ff-muted">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ff.empty-state icon="bi-clipboard-data" :title="__('No matching entries')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($logs->hasPages())<div class="ff-table-footer">{{ $logs->links() }}</div>@endif
    </div>
</x-layouts.super-admin>
