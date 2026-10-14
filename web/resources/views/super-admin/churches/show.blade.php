<x-layouts.super-admin :title="$church->name">
    <x-ff.page-header :title="$church->name" :breadcrumbs="[__('Churches') => route('super-admin.churches.index'), $church->name => null]">
        <a href="{{ route('churches.show', $church) }}" target="_blank" class="btn btn-ff-ghost"><i class="bi bi-box-arrow-up-right"></i> {{ __('Public page') }}</a>
        <a href="{{ route('super-admin.churches.manage', $church) }}" class="btn btn-ff-primary"><i class="bi bi-box-arrow-in-right"></i> {{ __('Manage this church') }}</a>
    </x-ff.page-header>

    <div class="row g-4">
        <div class="col-lg-8 d-grid gap-4 align-content-start">
            <div class="ff-card">
                <div class="d-flex gap-3 align-items-start">
                    <x-ff.church-avatar :church="$church" size="xl" />
                    <div class="flex-grow-1">
                        <div class="d-flex gap-2 align-items-center flex-wrap mb-1">
                            <x-ff.status :status="$church->status" />
                            @if ($church->denomination)<span class="ff-badge">{{ $church->denomination }}</span>@endif
                            @if ($church->setup_completed_at)<span class="ff-badge ff-badge-success"><i class="bi bi-check2"></i> {{ __('Setup complete') }}</span>@endif
                        </div>
                        <p class="mb-1">{{ $church->description ?: __('No description yet.') }}</p>
                        <div class="small text-ff-muted">
                            <i class="bi bi-geo-alt"></i> {{ $church->address }} {{ $church->location }} ·
                            <i class="bi bi-telephone"></i> {{ $church->phone ?: '—' }} ·
                            <i class="bi bi-envelope"></i> {{ $church->email ?: '—' }}
                        </div>
                        <div class="small mt-1"><i class="bi bi-globe"></i> <a href="{{ ff_church_url($church) }}" target="_blank">{{ ff_church_url($church) }}</a></div>
                    </div>
                </div>
                @if ($church->application_notes)
                    <div class="ff-card ff-card-gray ff-card-sm mt-3">
                        <div class="small fw-semibold text-ff-black mb-1">{{ __('Application notes') }}</div>
                        <div class="small">{{ $church->application_notes }}</div>
                    </div>
                @endif
                @if ($church->status_reason)
                    <div class="alert alert-warning small mt-3 mb-0">{{ __('Status reason') }}: {{ $church->status_reason }}</div>
                @endif
            </div>

            <div class="row g-3">
                <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Staff')" :value="$stats['staff']" icon="bi-person-badge" /></div>
                <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Branches')" :value="$stats['branches']" icon="bi-diagram-2" /></div>
                @isset($stats['subscribers'])
                    <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Subscribers')" :value="number_format($stats['subscribers'])" icon="bi-people" /></div>
                @endisset
                @isset($stats['giving_total'])
                    <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Giving (all time)')" :value="ff_money($stats['giving_total'])" icon="bi-cash-coin" /></div>
                @endisset
                <div class="col-sm-6 col-xl-3"><x-ff.stat-card :label="__('Storage used')" :value="$stats['storage_mb'].' MB'" icon="bi-hdd" /></div>
            </div>

            <x-ff.card :title="__('Staff')" flush>
                <div class="table-responsive">
                    <table class="table ff-table">
                        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Access') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($church->staffMembers as $member)
                                <tr>
                                    <td><strong class="text-ff-black">{{ $member->user?->name }}</strong><div class="small text-ff-muted">{{ $member->user?->email }}</div></td>
                                    <td>{{ $member->is_admin ? __('Church admin') : ($member->role?->name ?? '—') }}</td>
                                    <td><x-ff.status :status="$member->status" /></td>
                                    <td class="text-end">
                                        @if ($member->user && ! $member->user->isSuperAdmin())
                                            <form method="POST" action="{{ route('super-admin.impersonate.start', $member->user) }}" data-ff-confirm="{{ __('Impersonate :name? This is recorded in the audit log.', ['name' => $member->user->name]) }}">
                                                @csrf
                                                <button class="btn btn-sm btn-ff-soft"><i class="bi bi-incognito"></i> {{ __('Impersonate') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-ff.card>

            <x-ff.card :title="__('Recent activity')">
                @forelse ($activity as $log)
                    <div class="d-flex gap-3 py-2 border-bottom small">
                        <code class="text-ff-primary text-nowrap">{{ $log->action }}</code>
                        <span class="flex-grow-1 text-truncate">{{ $log->description }}</span>
                        <span class="text-ff-muted text-nowrap">{{ $log->user?->name ?? __('System') }} · {{ $log->created_at->diffForHumans(short: true) }}</span>
                    </div>
                @empty
                    <p class="text-ff-muted small mb-0">{{ __('No activity yet.') }}</p>
                @endforelse
            </x-ff.card>
        </div>

        <aside class="col-lg-4 d-grid gap-4 align-content-start">
            <div class="ff-card">
                <h2 class="h6 mb-3">{{ __('Lifecycle') }}</h2>
                <div class="d-grid gap-2">
                    @if ($church->status === 'pending')
                        <form method="POST" action="{{ route('super-admin.churches.approve', $church) }}">@csrf
                            <button class="btn btn-ff-primary w-100"><i class="bi bi-check2-circle"></i> {{ __('Approve church') }}</button>
                        </form>
                    @endif
                    @if (in_array($church->status, ['suspended', 'archived'], true))
                        <form method="POST" action="{{ route('super-admin.churches.reactivate', $church) }}">@csrf
                            <button class="btn btn-ff-primary w-100"><i class="bi bi-arrow-counterclockwise"></i> {{ __('Reactivate') }}</button>
                        </form>
                    @endif
                    @if (in_array($church->status, ['active', 'pending'], true))
                        <form method="POST" action="{{ route('super-admin.churches.suspend', $church) }}" data-ff-confirm="{{ __('Suspend this church? Public pages and giving will be disabled.') }}">@csrf
                            <input type="text" name="reason" class="form-control form-control-sm mb-2" placeholder="{{ __('Reason (sent to the church)') }}" aria-label="{{ __('Reason') }}">
                            <button class="btn btn-outline-warning w-100 rounded-pill"><i class="bi bi-pause-circle"></i> {{ __('Suspend') }}</button>
                        </form>
                    @endif
                    @if ($church->status !== 'archived')
                        <form method="POST" action="{{ route('super-admin.churches.archive', $church) }}" data-ff-confirm="{{ __('Archive this church? Its staff will lose access.') }}">@csrf
                            <button class="btn btn-ff-ghost w-100 text-danger"><i class="bi bi-archive"></i> {{ __('Archive') }}</button>
                        </form>
                    @endif
                </div>
                <hr>
                <dl class="small mb-0 row">
                    <dt class="col-5 text-ff-muted fw-normal">{{ __('Applied by') }}</dt><dd class="col-7">{{ $church->owner?->name ?? '—' }}</dd>
                    <dt class="col-5 text-ff-muted fw-normal">{{ __('Created') }}</dt><dd class="col-7">{{ $church->created_at->format('j M Y') }}</dd>
                    <dt class="col-5 text-ff-muted fw-normal">{{ __('Approved') }}</dt><dd class="col-7">{{ $church->approved_at?->format('j M Y') ?? '—' }} @if ($church->approver)<span class="text-ff-muted">· {{ $church->approver->name }}</span>@endif</dd>
                </dl>
            </div>

            <form method="POST" action="{{ route('super-admin.churches.update', $church) }}" class="ff-card">
                @csrf @method('PATCH')
                <h2 class="h6 mb-3">{{ __('Platform settings') }}</h2>
                <x-ff.checkbox name="is_featured" :label="__('Featured in directory')" :checked="$church->is_featured" switch />
                <x-ff.input name="custom_domain" :label="__('Custom domain')" :value="$church->custom_domain" placeholder="gracechurch.org" :help="__('Point the domain’s A record to the platform IP, then mark it verified.')" />
                <x-ff.checkbox name="custom_domain_verified" :label="__('Domain verified (DNS configured)')" :checked="$church->custom_domain_verified" switch />
                <button class="btn btn-ff-outline w-100 mt-2">{{ __('Save') }}</button>
            </form>
        </aside>
    </div>
</x-layouts.super-admin>
