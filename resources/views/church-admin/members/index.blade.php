<x-layouts.church-admin :church="$church" :title="__('Members')">
    <x-ff.page-header :title="__('Members')" :subtitle="__('Everyone who follows :church.', ['church' => $church->name])">
        <a href="{{ route('church-admin.members.export', array_merge(['church' => $church], request()->only('status', 'stage', 'branch', 'q'))) }}" class="btn btn-ff-outline"><i class="bi bi-download"></i> {{ __('Export CSV') }}</a>
        @if (Route::has('church-admin.notifications.create'))
            <a href="{{ route('church-admin.notifications.create', $church) }}" class="btn btn-ff-primary"><i class="bi bi-send"></i> {{ __('Message members') }}</a>
        @endif
    </x-ff.page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Active members')" :value="number_format($counts['active'] ?? 0)" icon="bi-people" primary /></div>
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Awaiting approval')" :value="number_format($counts['pending'] ?? 0)" icon="bi-hourglass-split" :href="route('church-admin.members.index', [$church, 'status' => 'pending'])" /></div>
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Visitors to follow up')" :value="number_format($stageCounts['visitor'] ?? 0)" icon="bi-person-walking" :href="route('church-admin.members.index', [$church, 'stage' => 'visitor'])" /></div>
        <div class="col-6 col-lg-3"><x-ff.stat-card :label="__('Leaders')" :value="number_format($stageCounts['leader'] ?? 0)" icon="bi-award" :href="route('church-admin.members.index', [$church, 'stage' => 'leader'])" /></div>
    </div>

    <div class="ff-table-wrap">
        <form method="GET" class="ff-table-toolbar">
            <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" style="max-width: 240px" placeholder="{{ __('Name or phone…') }}" aria-label="{{ __('Search') }}">
            <select name="status" class="form-select form-select-sm" style="max-width: 170px" aria-label="{{ __('Status') }}">
                <option value="">{{ __('Active & pending') }}</option>
                @foreach (['active', 'pending', 'left', 'blocked'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ __(ucfirst($s)) }}</option>@endforeach
            </select>
            <select name="stage" class="form-select form-select-sm" style="max-width: 170px" aria-label="{{ __('Stage') }}">
                <option value="">{{ __('All stages') }}</option>
                @foreach (\App\Models\Subscription::STAGES as $key => $label)<option value="{{ $key }}" @selected(request('stage') === $key)>{{ __($label) }}</option>@endforeach
            </select>
            @if ($branches->count() > 1)
                <select name="branch" class="form-select form-select-sm" style="max-width: 170px" aria-label="{{ __('Branch') }}">
                    <option value="">{{ __('All branches') }}</option>
                    @foreach ($branches as $id => $name)<option value="{{ $id }}" @selected((string) request('branch') === (string) $id)>{{ $name }}</option>@endforeach
                </select>
            @endif
            <button class="btn btn-sm btn-ff-primary">{{ __('Filter') }}</button>
        </form>
        <div class="table-responsive">
            <table class="table ff-table">
                <thead><tr><th>{{ __('Member') }}</th><th>{{ __('Phone') }}</th><th>{{ __('Stage') }}</th><th>{{ __('Branch') }}</th><th>{{ __('Status') }}</th><th>{{ __('Since') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($members as $member)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <x-ff.avatar :user="$member->user" size="sm" />
                                    <div class="min-w-0">
                                        <div class="fw-semibold text-ff-black">{{ $member->user?->name }}</div>
                                        <div class="small-xs text-ff-muted">{{ $member->share_email ? $member->user?->email : __('Email private') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="small">{{ $member->user?->phone ?: '—' }}</td>
                            <td>
                                <form method="POST" action="{{ route('church-admin.members.update', [$church, $member]) }}">@csrf @method('PUT')
                                    <select name="stage" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="{{ __('Stage') }}">
                                        @foreach (\App\Models\Subscription::STAGES as $key => $label)<option value="{{ $key }}" @selected($member->stage === $key)>{{ __($label) }}</option>@endforeach
                                    </select>
                                </form>
                            </td>
                            <td class="small">{{ $member->branch?->name ?? '—' }}</td>
                            <td><x-ff.status :status="$member->status" /></td>
                            <td class="small text-ff-muted text-nowrap">{{ $member->subscribed_at?->format('j M Y') }}</td>
                            <td class="text-end text-nowrap">
                                @if ($member->status === 'pending')
                                    <form method="POST" action="{{ route('church-admin.members.update', [$church, $member]) }}" class="d-inline">@csrf @method('PUT')
                                        <input type="hidden" name="action" value="approve"><button class="btn btn-sm btn-ff-primary">{{ __('Approve') }}</button>
                                    </form>
                                @endif
                                @if ($member->status === 'blocked')
                                    <form method="POST" action="{{ route('church-admin.members.update', [$church, $member]) }}" class="d-inline">@csrf @method('PUT')
                                        <input type="hidden" name="action" value="unblock"><button class="btn btn-sm btn-ff-soft">{{ __('Unblock') }}</button>
                                    </form>
                                @elseif (in_array($member->status, ['active', 'pending'], true))
                                    <form method="POST" action="{{ route('church-admin.members.update', [$church, $member]) }}" class="d-inline" data-ff-confirm="{{ __('Block :name from this church?', ['name' => $member->user?->name]) }}">@csrf @method('PUT')
                                        <input type="hidden" name="action" value="block"><button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Block') }}"><i class="bi bi-slash-circle"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-ff.empty-state icon="bi-people" :title="__('No members found')" :message="__('Share your church page so people can subscribe.')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($members->hasPages())<div class="ff-table-footer">{{ $members->links() }}</div>@endif
    </div>
</x-layouts.church-admin>
