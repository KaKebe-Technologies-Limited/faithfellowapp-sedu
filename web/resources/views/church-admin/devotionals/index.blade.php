<x-layouts.church-admin :church="$church" :title="__('Devotionals')">
    <x-ff.page-header :title="__('Devotionals')" :subtitle="__('Daily devotionals go out to subscribers on their publish date.')">
        <a href="{{ route('church-admin.devotionals.create', $church) }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('New devotional') }}</a>
    </x-ff.page-header>

    <div class="ff-table-wrap">
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Title') }}</th><th>{{ __('Scripture') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($devotionals as $d)
                        <tr>
                            <td class="text-nowrap small">{{ $d->publish_date->format('D j M Y') }} @if ($d->publish_date->isToday())<span class="ff-badge ff-badge-primary">{{ __('Today') }}</span>@endif</td>
                            <td class="fw-semibold text-ff-black">{{ $d->title }} @if ($d->audio_src)<i class="bi bi-headphones text-ff-primary"></i>@endif</td>
                            <td class="small">{{ $d->scripture }}</td>
                            <td><x-ff.status :status="$d->publish_date->isFuture() && $d->status === 'published' ? 'scheduled' : $d->status" /></td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('church-admin.devotionals.edit', [$church, $d]) }}" class="btn btn-sm btn-ff-soft">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('church-admin.devotionals.destroy', [$church, $d]) }}" class="d-inline" data-ff-confirm="{{ __('Delete?') }}">@csrf @method('DELETE')
                                    <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-ff.empty-state icon="bi-book" :title="__('No devotionals yet')" :message="__('Write ahead and schedule a week of devotionals at once.')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($devotionals->hasPages())<div class="ff-table-footer">{{ $devotionals->links() }}</div>@endif
    </div>
</x-layouts.church-admin>
