<x-layouts.church-admin :church="$church" :title="__('Comments')">
    <x-ff.page-header :title="__('Comments')" :subtitle="__('Comments on posts, sermons, campaigns, testimonies and prayers. Hidden comments disappear for members.')" />

    <div class="d-flex flex-wrap gap-3 justify-content-between mb-3">
        <nav class="ff-pills" aria-label="{{ __('Status') }}">
            @foreach (['' => __('All'), 'visible' => __('Visible'), 'flagged' => __('Flagged'), 'hidden' => __('Hidden')] as $value => $label)
                <a href="{{ route('church-admin.comments.index', [$church, 'status' => $value ?: null]) }}" @class(['ff-pill', 'active' => ($status ?? '') === $value])>
                    {{ $label }}@if ($value && ($counts[$value] ?? 0)) <span class="opacity-75">{{ $counts[$value] }}</span>@endif
                </a>
            @endforeach
        </nav>
        <form method="GET" class="d-flex gap-2">
            @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="{{ __('Search comments…') }}" aria-label="{{ __('Search comments') }}">
        </form>
    </div>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Comment') }}</th><th>{{ __('On') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($comments as $c)
                        @php
                            $target = $c->commentable;
                            $kind = $target ? Str::headline(class_basename($target)) : __('Deleted');
                            $label = $target?->title ?? Str::limit($target?->body ?? '', 50);
                        @endphp
                        <tr>
                            <td style="max-width: 460px">
                                <div class="d-flex gap-2">
                                    <x-ff.avatar :user="$c->user" size="xs" />
                                    <div><div class="small fw-semibold text-ff-black">{{ $c->user?->name ?? __('Deleted user') }} <span class="text-ff-muted fw-normal">· {{ $c->created_at->diffForHumans() }}</span></div><div class="small">{{ $c->body }}</div></div>
                                </div>
                            </td>
                            <td class="small"><span class="ff-tag">{{ $kind }}</span> {{ Str::limit($label, 50) }}</td>
                            <td><x-ff.status :status="$c->status" /></td>
                            <td class="text-end text-nowrap">
                                <form method="POST" action="{{ route('church-admin.comments.update', [$church, $c]) }}" class="d-inline">@csrf @method('PUT')
                                    @if ($c->status === 'visible')
                                        <button name="status" value="hidden" class="btn btn-sm btn-ff-ghost" title="{{ __('Hide') }}"><i class="bi bi-eye-slash"></i></button>
                                    @else
                                        <button name="status" value="visible" class="btn btn-sm btn-ff-soft">{{ __('Show') }}</button>
                                    @endif
                                </form>
                                <form method="POST" action="{{ route('church-admin.comments.destroy', [$church, $c]) }}" class="d-inline" data-ff-confirm="{{ __('Delete?') }}">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-ff.empty-state icon="bi-chat-left-dots" :title="__('No comments')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($comments->hasPages())<div class="ff-table-footer">{{ $comments->links() }}</div>@endif
    </div>
</x-layouts.church-admin>
