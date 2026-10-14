<x-layouts.member :title="$group->name">
    <x-ff.page-header :title="$group->name" :subtitle="$group->church->name.' · '.__(\App\Models\SmallGroup::TYPES[$group->type] ?? 'Group')"
        :breadcrumbs="[__('Small groups') => route('groups.index'), $group->name => null]">
        @if ($membership?->status === 'active')
            <form method="POST" action="{{ route('groups.leave', $group) }}" data-ff-confirm="{{ __('Leave this group?') }}">@csrf @method('DELETE')
                <button class="btn btn-ff-ghost">{{ __('Leave group') }}</button>
            </form>
        @elseif ($membership?->status === 'pending')
            <span class="ff-badge ff-badge-warning align-self-center">{{ __('Request pending') }}</span>
        @elseif ($group->is_open && $group->status === 'active')
            <form method="POST" action="{{ route('groups.join', $group) }}">@csrf
                <button class="btn btn-ff-primary"><i class="bi bi-person-plus"></i> {{ __('Ask to join') }}</button>
            </form>
        @endif
        @if (auth()->user()->canInChurch($group->church, 'groups.manage'))
            <a href="{{ route('church-admin.small-groups.edit', [$group->church, $group]) }}" class="btn btn-ff-soft"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
        @endif
    </x-ff.page-header>

    <div class="row g-4">
        <div class="col-lg-8 d-grid gap-4 align-content-start">
            <x-ff.card>
                @if ($group->description)<p style="white-space: pre-line">{{ $group->description }}</p>@endif
                <ul class="list-unstyled d-grid gap-2 mb-0">
                    @if ($group->schedule)<li><i class="bi bi-calendar-week text-ff-primary"></i> {{ $group->schedule }}</li>@endif
                    @if ($group->location)<li><i class="bi bi-geo-alt text-ff-primary"></i> {{ $group->location }}</li>@endif
                    @if ($group->leader)<li class="d-flex align-items-center gap-2"><x-ff.avatar :user="$group->leader" size="xs" /> {{ __('Led by :name', ['name' => $group->leader->name]) }}</li>@endif
                </ul>
                @if ($group->lat && $group->lng)
                    <div class="rounded-3 overflow-hidden mt-3" data-ff-map="{{ json_encode([['lat' => $group->lat, 'lng' => $group->lng, 'label' => $group->name, 'sub' => $group->location]]) }}" style="height: 220px"></div>
                @endif
            </x-ff.card>

            @if ($canLead)
                <x-ff.card :title="__('Group announcement')" :subtitle="__('Only members of this group are notified.')">
                    <form method="POST" action="{{ route('groups.announcements.store', $group) }}">@csrf
                        <x-ff.input name="title" :label="__('Title')" maxlength="200" required />
                        <x-ff.textarea name="body" :label="__('Message')" rows="3" maxlength="3000" required />
                        <button class="btn btn-ff-primary"><i class="bi bi-megaphone"></i> {{ __('Send to group') }}</button>
                    </form>
                </x-ff.card>
            @endif

            @if ($announcements->isNotEmpty())
                <x-ff.card :title="__('Announcements')">
                    @foreach ($announcements as $a)
                        <div class="py-2 @if (! $loop->last) border-bottom @endif">
                            <div class="fw-semibold text-ff-black">{{ $a->title }}</div>
                            <div class="small-xs text-ff-muted mb-1">{{ $a->published_at?->diffForHumans() }}</div>
                            <div class="small">{!! ff_safe_html($a->body) !!}</div>
                        </div>
                    @endforeach
                </x-ff.card>
            @endif

            @if ($canLead)
                <x-ff.card :title="__('Record a meeting')" :subtitle="__('Tick who was present. Everyone else is marked absent.')">
                    <form method="POST" action="{{ route('groups.meetings.store', $group) }}">@csrf
                        <div class="row g-2">
                            <div class="col-sm-4"><x-ff.input type="date" name="meeting_date" :label="__('Date')" :value="today()->toDateString()" max="{{ today()->toDateString() }}" required /></div>
                            <div class="col-sm-8"><x-ff.input name="topic" :label="__('Topic')" maxlength="200" /></div>
                            <div class="col-sm-4"><x-ff.input type="number" name="visitors" :label="__('Visitors')" min="0" value="0" /></div>
                            <div class="col-sm-8"><x-ff.input type="number" name="offering_amount" :label="__('Cell offering (UGX)')" min="0" step="500" value="0" :help="__('Collected for the central fund.')" /></div>
                        </div>
                        <div class="form-label">{{ __('Attendance') }}</div>
                        <div class="ff-attendance-grid mb-3">
                            @foreach ($memberships as $m)
                                <label><input type="checkbox" name="present[]" value="{{ $m->user_id }}" class="form-check-input m-0" checked> <x-ff.avatar :user="$m->user" size="xs" /> <span class="text-truncate">{{ $m->user?->name }}</span></label>
                            @endforeach
                        </div>
                        <x-ff.textarea name="notes" :label="__('Notes')" rows="2" maxlength="2000" />
                        <button class="btn btn-ff-primary"><i class="bi bi-check2-square"></i> {{ __('Save meeting') }}</button>
                    </form>
                </x-ff.card>
            @endif

            @if ($meetings->isNotEmpty())
                <x-ff.card :title="__('Recent meetings')" flush>
                    <div class="table-responsive">
                        <table class="table ff-table mb-0">
                            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Topic') }}</th><th class="text-end">{{ __('Present') }}</th><th class="text-end">{{ __('Visitors') }}</th></tr></thead>
                            <tbody>
                                @foreach ($meetings as $meeting)
                                    <tr><td>{{ $meeting->meeting_date->format('D j M') }}</td><td>{{ $meeting->topic ?: '—' }}</td><td class="text-end">{{ $meeting->present_count }}</td><td class="text-end">{{ $meeting->visitors }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ff.card>
            @endif
        </div>

        <aside class="col-lg-4 d-grid gap-4 align-content-start">
            @if ($pendingMembers->isNotEmpty())
                <x-ff.card :title="__('Join requests')">
                    @foreach ($pendingMembers as $m)
                        <div class="d-flex align-items-center gap-2 py-1">
                            <x-ff.avatar :user="$m->user" size="sm" />
                            <span class="flex-grow-1 small text-truncate">{{ $m->user?->name }}</span>
                            <form method="POST" action="{{ route('groups.members.update', [$group, $m]) }}">@csrf @method('PUT')
                                <button name="action" value="approve" class="btn btn-sm btn-ff-primary" aria-label="{{ __('Approve') }}"><i class="bi bi-check-lg"></i></button>
                                <button name="action" value="remove" class="btn btn-sm btn-ff-ghost" aria-label="{{ __('Decline') }}"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </div>
                    @endforeach
                </x-ff.card>
            @endif

            <x-ff.card :title="trans_choice(':count member|:count members', $memberships->count())">
                @foreach ($memberships as $m)
                    <div class="d-flex align-items-center gap-2 py-1">
                        <x-ff.avatar :user="$m->user" size="sm" />
                        <span class="flex-grow-1 min-w-0">
                            <span class="d-block small text-ff-black text-truncate">{{ $m->user?->name }}</span>
                            @if ($m->role !== 'member')<span class="small-xs text-ff-primary">{{ __(ucfirst($m->role)) }}</span>@endif
                        </span>
                        @if ($canLead && $m->user_id !== auth()->id())
                            <div class="dropdown">
                                <button class="btn btn-sm btn-ff-ghost" data-bs-toggle="dropdown" aria-label="{{ __('Member options') }}"><i class="bi bi-three-dots"></i></button>
                                <form method="POST" action="{{ route('groups.members.update', [$group, $m]) }}" class="dropdown-menu dropdown-menu-end">@csrf @method('PUT')
                                    @foreach (['member' => __('Make member'), 'assistant' => __('Make assistant'), 'leader' => __('Make leader')] as $role => $label)
                                        @if ($m->role !== $role)<button name="action" value="{{ $role }}" class="dropdown-item">{{ $label }}</button>@endif
                                    @endforeach
                                    <button name="action" value="remove" class="dropdown-item text-danger">{{ __('Remove from group') }}</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @endforeach

                @if ($canLead && $candidates->isNotEmpty())
                    <form method="POST" action="{{ route('groups.members.store', $group) }}" class="d-flex gap-2 mt-3 pt-3 border-top">@csrf
                        <select name="user_id" class="form-select form-select-sm" required aria-label="{{ __('Add member') }}">
                            <option value="">{{ __('Add a church member…') }}</option>
                            @foreach ($candidates as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </select>
                        <button class="btn btn-sm btn-ff-primary">{{ __('Add') }}</button>
                    </form>
                @endif
            </x-ff.card>

            @if ($membership?->status === 'active' || $canLead)
                <a href="{{ route('give.create', [$group->church, 'group' => $group->id]) }}" class="btn btn-ff-soft"><i class="bi bi-heart"></i> {{ __('Give through this group') }}</a>
            @endif
        </aside>
    </div>
</x-layouts.member>
