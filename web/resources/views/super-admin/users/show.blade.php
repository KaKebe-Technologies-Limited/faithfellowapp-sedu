<x-layouts.super-admin :title="$user->name">
    <x-ff.page-header :title="$user->name" :breadcrumbs="[__('Users') => route('super-admin.users.index'), $user->name => null]">
        @unless ($user->isSuperAdmin() || $user->is(auth()->user()))
            <form method="POST" action="{{ route('super-admin.impersonate.start', $user) }}" data-ff-confirm="{{ __('Log in as :name? This is recorded in the audit log.', ['name' => $user->name]) }}">@csrf
                <button class="btn btn-ff-soft"><i class="bi bi-person-badge"></i> {{ __('Impersonate') }}</button>
            </form>
        @endunless
    </x-ff.page-header>

    <div class="row g-4">
        <aside class="col-lg-4 d-grid gap-4 align-content-start">
            <div class="ff-card text-center">
                <x-ff.avatar :user="$user" size="xl" class="mb-2" />
                <div class="fw-bold text-ff-black">{{ $user->name }}</div>
                <div class="small text-ff-muted">{{ $user->email }}</div>
                <div class="mt-2"><x-ff.status :status="$user->status" /> @foreach ($user->roles as $role)<span class="ff-badge">{{ Str::headline($role->name) }}</span>@endforeach</div>
                <ul class="list-unstyled small text-start mt-3 mb-0 d-grid gap-1">
                    <li><i class="bi bi-telephone text-ff-primary"></i> {{ $user->phone ?? '—' }}</li>
                    <li><i class="bi bi-geo-alt text-ff-primary"></i> {{ collect([$user->city, $user->country])->filter()->implode(', ') ?: '—' }}</li>
                    <li><i class="bi bi-calendar text-ff-primary"></i> {{ __('Joined :d', ['d' => $user->created_at->format('j M Y')]) }}</li>
                    <li><i class="bi bi-box-arrow-in-right text-ff-primary"></i> {{ $user->last_login_at ? __('Last login :d', ['d' => $user->last_login_at->diffForHumans()]) : __('Never logged in') }}</li>
                    <li><i class="bi bi-envelope-check text-ff-primary"></i> {{ $user->email_verified_at ? __('Email verified') : __('Email not verified') }}</li>
                    <li><i class="bi bi-shield-lock text-ff-primary"></i> {{ $user->two_factor_enabled ? __('Two-factor on') : __('Two-factor off') }}</li>
                    <li><i class="bi bi-phone-vibrate text-ff-primary"></i> {{ trans_choice(':count push device|:count push devices', $devices) }}</li>
                    <li><i class="bi bi-heart text-ff-primary"></i> {{ __(':n gifts · :total', ['n' => $giving->n, 'total' => ff_money($giving->total)]) }}</li>
                </ul>
            </div>

            @unless ($user->is(auth()->user()))
                <form method="POST" action="{{ route('super-admin.users.update', $user) }}" class="ff-card d-grid gap-2">@csrf @method('PATCH')
                    <h2 class="h6 mb-1">{{ __('Account actions') }}</h2>
                    @if ($user->status === 'active')
                        @unless ($user->isSuperAdmin())<button name="action" value="suspend" class="btn btn-ff-ghost text-danger" onclick="return confirm(@js(__('Suspend this account? They will be signed out everywhere.')))"><i class="bi bi-person-x"></i> {{ __('Suspend account') }}</button>@endunless
                    @else
                        <button name="action" value="reactivate" class="btn btn-ff-soft"><i class="bi bi-person-check"></i> {{ __('Reactivate account') }}</button>
                    @endif
                    @unless ($user->email_verified_at)<button name="action" value="verify" class="btn btn-ff-ghost">{{ __('Mark email as verified') }}</button>@endunless
                    @if ($user->two_factor_enabled)<button name="action" value="reset_2fa" class="btn btn-ff-ghost">{{ __('Reset two-factor') }}</button>@endif
                </form>
            @endunless
        </aside>

        <div class="col-lg-8 d-grid gap-4 align-content-start">
            <x-ff.card :title="__('Churches followed')" flush>
                <div class="table-responsive">
                    <table class="table ff-table mb-0">
                        <thead><tr><th>{{ __('Church') }}</th><th>{{ __('Stage') }}</th><th>{{ __('Since') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($subscriptions as $s)
                                <tr><td>{{ $s->church?->name }}</td><td class="small">{{ Str::headline($s->stage) }}</td><td class="small">{{ $s->subscribed_at?->format('j M Y') }}</td><td><x-ff.status :status="$s->status" /></td></tr>
                            @empty
                                <tr><td colspan="4" class="small text-ff-muted">{{ __('Not following any church.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ff.card>

            @if ($staff->isNotEmpty())
                <x-ff.card :title="__('Staff roles')" flush>
                    <div class="table-responsive">
                        <table class="table ff-table mb-0">
                            <tbody>
                                @foreach ($staff as $s)
                                    <tr><td>{{ $s->church?->name }}</td><td class="small">{{ $s->is_admin ? __('Church admin') : ($s->role?->name ?? __('Staff')) }}@if ($s->title) · {{ $s->title }}@endif</td><td><x-ff.status :status="$s->status" /></td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ff.card>
            @endif

            <x-ff.card :title="__('Recent admin activity')">
                @forelse ($activity as $log)
                    <div class="small py-1 @if (! $loop->first) border-top @endif"><code>{{ $log->action }}</code> {{ $log->description }} <span class="text-ff-muted">· {{ $log->created_at?->diffForHumans() }}</span></div>
                @empty
                    <p class="small text-ff-muted mb-0">{{ __('No admin actions recorded.') }}</p>
                @endforelse
            </x-ff.card>
        </div>
    </div>
</x-layouts.super-admin>
