<x-layouts.church-admin :church="$church" :title="__('Announcements')">
    <x-ff.page-header :title="__('Announcements')" :subtitle="__('Target everyone, members, staff, a branch or a small group — in-app, email, SMS or WhatsApp.')">
        <a href="{{ route('church-admin.announcements.create', $church) }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('New announcement') }}</a>
    </x-ff.page-header>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Announcement') }}</th><th>{{ __('Audience') }}</th><th>{{ __('Channels') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($announcements as $a)
                        <tr>
                            <td>
                                <div class="fw-semibold text-ff-black">@if ($a->priority === 'urgent')<i class="bi bi-exclamation-triangle-fill text-danger"></i> @endif{{ $a->title }}</div>
                                <div class="small-xs text-ff-muted">{{ $a->published_at?->format('j M Y, H:i') }} @if ($a->expires_at)· {{ __('until :d', ['d' => $a->expires_at->format('j M')]) }}@endif</div>
                            </td>
                            <td><span class="ff-badge">{{ __(ucfirst($a->audience)) }}@if ($a->branch) · {{ $a->branch->name }}@endif</span></td>
                            <td class="small">{{ collect(['in-app'])->merge($a->channels ?? [])->implode(', ') }}</td>
                            <td><x-ff.status :status="$a->status" /> @if ($a->notified_at)<i class="bi bi-send-check text-success" title="{{ __('Sent') }}"></i>@endif</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('church-admin.announcements.edit', [$church, $a]) }}" class="btn btn-sm btn-ff-soft">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('church-admin.announcements.destroy', [$church, $a]) }}" class="d-inline" data-ff-confirm="{{ __('Delete?') }}">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ff.empty-state icon="bi-megaphone" :title="__('No announcements yet')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($announcements->hasPages())<div class="ff-table-footer">{{ $announcements->links() }}</div>@endif
    </div>
</x-layouts.church-admin>
