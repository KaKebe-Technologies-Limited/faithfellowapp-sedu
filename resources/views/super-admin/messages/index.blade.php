<x-layouts.super-admin :title="__('Contact inbox')">
    <x-ff.page-header :title="__('Contact inbox')" :subtitle="__('Messages from the public contact form.')" />

    <div class="d-flex flex-wrap gap-3 justify-content-between mb-3">
        <nav class="ff-pills" aria-label="{{ __('Status') }}">
            @foreach (['' => __('All'), 'new' => __('New'), 'read' => __('Read'), 'replied' => __('Replied')] as $value => $label)
                <a href="{{ route('super-admin.messages.index', array_filter(['status' => $value])) }}" @class(['ff-pill', 'active' => ($status ?? '') === $value])>{{ $label }}@if ($value && ($counts[$value] ?? 0)) <span class="opacity-75">{{ $counts[$value] }}</span>@endif</a>
            @endforeach
        </nav>
        <form method="GET" class="d-flex gap-2">
            @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Email or subject') }}" aria-label="{{ __('Search messages') }}">
        </form>
    </div>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('From') }}</th><th>{{ __('Message') }}</th><th>{{ __('Received') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @forelse ($messages as $m)
                        <tr @class(['fw-semibold' => $m->status === 'new'])>
                            <td><a href="{{ route('super-admin.messages.show', $m) }}" class="text-ff-black">{{ $m->name }}</a><div class="small-xs text-ff-muted fw-normal">{{ $m->email }}</div></td>
                            <td class="small"><a href="{{ route('super-admin.messages.show', $m) }}" class="text-ff-black">{{ $m->subject ?: __('(no subject)') }}</a><div class="text-ff-muted fw-normal">{{ Str::limit($m->message, 90) }}</div></td>
                            <td class="small fw-normal">{{ $m->created_at->diffForHumans() }}</td>
                            <td><x-ff.status :status="$m->status === 'new' ? 'open' : ($m->status === 'replied' ? 'resolved' : 'pending')">{{ __(ucfirst($m->status)) }}</x-ff.status></td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-ff.empty-state icon="bi-inbox" :title="__('Inbox zero')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($messages->hasPages())<div class="ff-table-footer">{{ $messages->links() }}</div>@endif
    </div>
</x-layouts.super-admin>
