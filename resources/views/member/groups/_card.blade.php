{{-- Small group card. Expects $group (with church, leader; members_count). Optional $pending (bool), $joinable (bool). --}}
<article class="ff-card ff-group-card">
    <div class="d-flex align-items-start gap-2">
        <div class="min-w-0 flex-grow-1">
            <div class="ff-group-type">{{ __(\App\Models\SmallGroup::TYPES[$group->type] ?? 'Group') }}</div>
            <h3 class="h5 mb-0"><a href="{{ route('groups.show', $group) }}" class="text-ff-black">{{ $group->name }}</a></h3>
            <div class="small text-ff-muted">{{ $group->church->name }}</div>
        </div>
        <x-ff.church-avatar :church="$group->church" size="sm" />
    </div>
    @if ($group->description)<p class="small mb-0">{{ Str::limit($group->description, 120) }}</p>@endif
    <ul class="list-unstyled small mb-0 d-grid gap-1">
        @if ($group->schedule)<li><i class="bi bi-calendar-week text-ff-primary"></i> {{ $group->schedule }}</li>@endif
        @if ($group->location)<li><i class="bi bi-geo-alt text-ff-primary"></i> {{ $group->location }}</li>@endif
        <li><i class="bi bi-people text-ff-primary"></i> {{ trans_choice(':count member|:count members', $group->members_count) }}@if ($group->leader) · {{ __('Led by :name', ['name' => $group->leader->name]) }}@endif</li>
    </ul>
    <div class="mt-auto d-flex gap-2">
        @if ($pending ?? false)
            <span class="ff-badge ff-badge-warning">{{ __('Request pending') }}</span>
        @elseif ($joinable ?? false)
            <form method="POST" action="{{ route('groups.join', $group) }}">@csrf
                <button class="btn btn-sm btn-ff-primary">{{ __('Ask to join') }}</button>
            </form>
        @endif
        <a href="{{ route('groups.show', $group) }}" class="btn btn-sm btn-ff-soft ms-auto">{{ __('View') }}</a>
    </div>
</article>
