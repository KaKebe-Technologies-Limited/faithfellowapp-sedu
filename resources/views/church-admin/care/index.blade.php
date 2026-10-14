<x-layouts.church-admin :church="$church" :title="__('Pastoral care')">
    <x-ff.page-header :title="__('Pastoral care')" :subtitle="__('Celebrations, follow-ups, visits, welfare and discipleship — all in one place.')">
        <a href="{{ route('church-admin.counseling.index', $church) }}" class="btn btn-ff-soft"><i class="bi bi-person-heart"></i> {{ __('Counseling') }}@if ($counseling->where('status', 'requested')->count()) <span class="ff-badge ff-badge-primary ms-1">{{ $counseling->where('status', 'requested')->count() }}</span>@endif</a>
    </x-ff.page-header>
    <p class="ff-confidential mb-4"><i class="bi bi-shield-lock"></i> {{ __('Confidential. Notes are encrypted and visible only to staff with pastoral care access.') }}</p>

    <form method="GET" action="{{ route('church-admin.care.index', $church) }}" class="ff-card ff-card-sm d-flex gap-2 align-items-center mb-4"
          x-data @submit.prevent="if ($refs.who.value) window.location = @js(route('church-admin.care.member', [$church, '__ID__'])).replace('__ID__', $refs.who.value)">
        <i class="bi bi-search text-ff-muted"></i>
        <select x-ref="who" class="form-select border-0" aria-label="{{ __('Open a member’s care record') }}">
            <option value="">{{ __('Open a member’s care record…') }}</option>
            @foreach ($people as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
        </select>
        <button class="btn btn-ff-primary btn-sm">{{ __('Open') }}</button>
    </form>

    <div class="row g-4">
        <div class="col-lg-4">
            <x-ff.card :title="__('Celebrations')" :subtitle="__('Next 14 days')">
                @foreach ($celebrations['birthdays'] as $u)
                    <div class="d-flex align-items-center gap-2 py-1">
                        <x-ff.avatar :user="$u" size="sm" />
                        <a href="{{ route('church-admin.care.member', [$church, $u->id]) }}" class="flex-grow-1 small text-ff-black text-truncate">{{ $u->name }}</a>
                        <span class="small-xs text-ff-muted">🎂 {{ $u->next_date->isToday() ? __('Today') : $u->next_date->format('D j M') }}</span>
                    </div>
                @endforeach
                @foreach ($celebrations['anniversaries'] as $u)
                    <div class="d-flex align-items-center gap-2 py-1">
                        <x-ff.avatar :user="$u" size="sm" />
                        <a href="{{ route('church-admin.care.member', [$church, $u->id]) }}" class="flex-grow-1 small text-ff-black text-truncate">{{ $u->name }}</a>
                        <span class="small-xs text-ff-muted">💍 {{ $u->next_date->isToday() ? __('Today') : $u->next_date->format('D j M') }}</span>
                    </div>
                @endforeach
                @if ($celebrations['birthdays']->isEmpty() && $celebrations['anniversaries']->isEmpty())
                    <p class="small text-ff-muted mb-0">{{ __('No birthdays or anniversaries coming up.') }}</p>
                @endif
            </x-ff.card>
        </div>

        <div class="col-lg-4">
            <x-ff.card :title="__('Follow up')" :subtitle="__('Visitors & new members, last 60 days')">
                @forelse ($followUps as $s)
                    <div class="d-flex align-items-center gap-2 py-1">
                        <x-ff.avatar :user="$s->user" size="sm" />
                        <span class="flex-grow-1 min-w-0">
                            <a href="{{ route('church-admin.care.member', [$church, $s->user_id]) }}" class="d-block small text-ff-black text-truncate">{{ $s->user?->name }}</a>
                            <span class="small-xs text-ff-muted">{{ Str::headline($s->stage) }} · {{ $s->subscribed_at?->diffForHumans() }}</span>
                        </span>
                        @if ($s->user?->phone)<a href="tel:{{ $s->user->phone }}" class="btn btn-sm btn-ff-ghost" aria-label="{{ __('Call') }}"><i class="bi bi-telephone"></i></a>@endif
                    </div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('No one waiting for a follow-up.') }}</p>
                @endforelse
            </x-ff.card>
        </div>

        <div class="col-lg-4">
            <x-ff.card :title="__('Upcoming home visits')">
                @forelse ($visits as $v)
                    <div class="d-flex align-items-center gap-2 py-1">
                        <x-ff.avatar :user="$v->member" size="sm" />
                        <span class="flex-grow-1 min-w-0">
                            <a href="{{ route('church-admin.care.member', [$church, $v->user_id]) }}" class="d-block small text-ff-black text-truncate">{{ $v->member?->name }}</a>
                            <span class="small-xs text-ff-muted">{{ $v->scheduled_at->format('D j M, g:i A') }}@if ($v->visitor) · {{ $v->visitor->name }}@endif</span>
                        </span>
                    </div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('No visits scheduled.') }}</p>
                @endforelse
                <button class="btn btn-sm btn-ff-soft mt-2" data-bs-toggle="modal" data-bs-target="#visit-modal"><i class="bi bi-plus"></i> {{ __('Schedule a visit') }}</button>
            </x-ff.card>
        </div>

        <div class="col-lg-6">
            <x-ff.card :title="__('Discipleship pathway')" :subtitle="__('Members who completed each step')">
                @php($max = max(1, $milestones->max('count')))
                @foreach ($milestones as $m)
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small"><span>{{ $m['label'] }}</span><strong class="text-ff-black">{{ $m['count'] }}</strong></div>
                        <x-ff.progress :value="$m['count'] / $max * 100" size="sm" />
                    </div>
                @endforeach
            </x-ff.card>
        </div>

        <div class="col-lg-6">
            <x-ff.card :title="__('Welfare support')" :subtitle="__(':amount given this year', ['amount' => ff_money($welfareTotal)])">
                <x-slot:actions><button class="btn btn-sm btn-ff-soft" data-bs-toggle="modal" data-bs-target="#welfare-modal"><i class="bi bi-plus"></i> {{ __('Record') }}</button></x-slot:actions>
                @forelse ($welfare as $w)
                    <div class="d-flex justify-content-between align-items-center py-1 small">
                        <span class="text-truncate">{{ $w->recipient }} <span class="ff-tag">{{ __(\App\Models\WelfareRecord::TYPES[$w->type] ?? $w->type) }}</span></span>
                        <span class="text-nowrap">{{ $w->amount > 0 ? ff_money($w->amount) : '' }} <span class="text-ff-muted">{{ $w->given_on->format('j M') }}</span></span>
                    </div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('No welfare support recorded yet.') }}</p>
                @endforelse
            </x-ff.card>
        </div>

        @if ($counseling->isNotEmpty())
            <div class="col-12">
                <x-ff.card :title="__('Counseling requests')" flush>
                    <x-slot:actions><a href="{{ route('church-admin.counseling.index', $church) }}" class="btn btn-sm btn-ff-soft">{{ __('Manage') }}</a></x-slot:actions>
                    <div class="table-responsive">
                        <table class="table ff-table mb-0">
                            <tbody>
                                @foreach ($counseling as $a)
                                    <tr>
                                        <td><span class="d-flex align-items-center gap-2"><x-ff.avatar :user="$a->member" size="xs" /> {{ $a->member?->name }}</span></td>
                                        <td class="small">{{ $a->scheduled_at?->format('D j M, g:i A') ?? __('Not scheduled') }}</td>
                                        <td><x-ff.status :status="$a->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ff.card>
            </div>
        @endif
    </div>

    @include('church-admin.care._modals', ['memberId' => null])
</x-layouts.church-admin>
