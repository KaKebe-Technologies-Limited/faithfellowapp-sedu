<x-layouts.church-admin :church="$church" :title="__('Prayer wall')">
    <x-ff.page-header :title="__('Prayer wall')" :subtitle="__('Every request shared with :church, including pastor-only ones. Hide anything inappropriate.', ['church' => $church->name])" />

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Open')" :value="number_format($counts['open'] ?? 0)" icon="bi-chat-heart" /></div>
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Pastor-only (open)')" :value="number_format($pastorOnly)" icon="bi-lock" :primary="$pastorOnly > 0" /></div>
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Answered')" :value="number_format($counts['answered'] ?? 0)" icon="bi-stars" /></div>
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Hidden')" :value="number_format($counts['hidden'] ?? 0)" icon="bi-eye-slash" /></div>
    </div>

    <div class="d-flex flex-wrap gap-3 justify-content-between mb-3">
        <nav class="ff-pills" aria-label="{{ __('Status') }}">
            @foreach (['' => __('All'), 'open' => __('Open'), 'answered' => __('Answered'), 'hidden' => __('Hidden')] as $value => $label)
                <a href="{{ route('church-admin.prayer.index', [$church, 'status' => $value ?: null, 'visibility' => request('visibility')]) }}" @class(['ff-pill', 'active' => request('status', '') === $value])>{{ $label }}</a>
            @endforeach
        </nav>
        <nav class="ff-pills" aria-label="{{ __('Visibility') }}">
            @foreach (['' => __('Any visibility'), 'pastor' => __('Pastors only'), 'church' => __('Members'), 'public' => __('Public')] as $value => $label)
                <a href="{{ route('church-admin.prayer.index', [$church, 'visibility' => $value ?: null, 'status' => request('status')]) }}" @class(['ff-pill', 'active' => request('visibility', '') === $value])>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Request') }}</th><th>{{ __('From') }}</th><th>{{ __('Visibility') }}</th><th class="text-end">{{ __('Prayed') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($prayers as $p)
                        <tr>
                            <td style="max-width: 420px">
                                @if ($p->title)<div class="fw-semibold text-ff-black">{{ $p->title }}</div>@endif
                                <div class="small">{{ Str::limit($p->body, 160) }}</div>
                                <div class="small-xs text-ff-muted">{{ $p->created_at->format('j M Y, H:i') }} @if ($p->chain_enabled)· <i class="bi bi-link-45deg"></i> {{ __('chain') }}@endif</div>
                            </td>
                            <td class="small">
                                {{ $p->user?->name ?? __('Deleted user') }}
                                @if ($p->is_anonymous)<div class="small-xs text-ff-muted"><i class="bi bi-incognito"></i> {{ __('anonymous to members') }}</div>@endif
                            </td>
                            <td><span class="ff-badge @if ($p->visibility === 'pastor') ff-badge-dark @endif">{{ ['public' => __('Public'), 'church' => __('Members'), 'pastor' => __('Pastors')][$p->visibility] }}</span></td>
                            <td class="text-end">{{ $p->prayed_count }}</td>
                            <td><x-ff.status :status="$p->status" /></td>
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('church-admin.prayer.update', [$church, $p]) }}" class="d-inline">@csrf @method('PUT')
                                    @if ($p->status === 'hidden')
                                        <button name="status" value="open" class="btn btn-sm btn-ff-soft">{{ __('Restore') }}</button>
                                    @else
                                        <button name="status" value="hidden" class="btn btn-sm btn-ff-ghost" title="{{ __('Hide from wall') }}"><i class="bi bi-eye-slash"></i></button>
                                        @if ($p->status === 'open')<button name="status" value="answered" class="btn btn-sm btn-ff-ghost" title="{{ __('Mark answered') }}"><i class="bi bi-stars"></i></button>@endif
                                    @endif
                                </form>
                                @if ($p->user && auth()->user()->canInChurch($church, 'care.manage'))
                                    <a href="{{ route('church-admin.care.member', [$church, $p->user_id]) }}" class="btn btn-sm btn-ff-ghost" title="{{ __('Care record') }}"><i class="bi bi-heart-pulse"></i></a>
                                @endif
                                <form method="POST" action="{{ route('church-admin.prayer.destroy', [$church, $p]) }}" class="d-inline" data-ff-confirm="{{ __('Delete this request?') }}">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-ff.empty-state icon="bi-chat-heart" :title="__('No prayer requests')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($prayers->hasPages())<div class="ff-table-footer">{{ $prayers->links() }}</div>@endif
    </div>
</x-layouts.church-admin>
