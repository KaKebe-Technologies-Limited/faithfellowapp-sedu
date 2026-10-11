<x-layouts.church-admin :church="$church" :title="__('Staff & roles')">
    <x-ff.page-header :title="__('Staff & roles')" :subtitle="__('Give each person only the access they need.')">
        <button class="btn btn-ff-primary" data-bs-toggle="modal" data-bs-target="#inviteStaff"><i class="bi bi-person-plus"></i> {{ __('Add staff') }}</button>
    </x-ff.page-header>

    @if (request('wizard'))
        <div class="alert alert-info rounded-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-people"></i> {{ __('Last step: invite your team. You can always do this later.') }}</span>
            <a href="{{ route('church-admin.dashboard', $church) }}" class="btn btn-sm btn-ff-primary">{{ __('Finish setup') }}</a>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="ff-table-wrap">
                <div class="ff-table-toolbar"><h2 class="h6 mb-0">{{ __('Team') }}</h2></div>
                <div class="table-responsive">
                    <table class="table ff-table">
                        <thead><tr><th>{{ __('Person') }}</th><th>{{ __('Access') }}</th><th>{{ __('Branch') }}</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($staff as $member)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <x-ff.avatar :user="$member->user" size="sm" />
                                            <div class="min-w-0">
                                                <div class="fw-semibold text-ff-black">{{ $member->user?->name }}</div>
                                                <div class="small-xs text-ff-muted">{{ $member->title ? $member->title.' · ' : '' }}{{ $member->user?->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($member->is_admin)
                                            <span class="ff-badge ff-badge-primary"><i class="bi bi-shield-check"></i> {{ __('Church admin') }}</span>
                                        @else
                                            <span class="ff-badge">{{ $member->role?->name ?? __('No role') }}</span>
                                        @endif
                                    </td>
                                    <td class="small">{{ $member->branch?->name ?? __('All') }}</td>
                                    <td class="text-end text-nowrap">
                                        <button class="btn btn-sm btn-ff-soft" data-bs-toggle="modal" data-bs-target="#editStaff{{ $member->id }}">{{ __('Edit') }}</button>
                                        @if ($member->user_id !== auth()->id())
                                            <form method="POST" action="{{ route('church-admin.staff.destroy', [$church, $member]) }}" class="d-inline" data-ff-confirm="{{ __('Remove :name from staff?', ['name' => $member->user?->name]) }}">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Remove') }}"><i class="bi bi-person-dash"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($staff->hasPages())<div class="ff-table-footer">{{ $staff->links() }}</div>@endif
            </div>
        </div>

        <div class="col-xl-4">
            <x-ff.card :title="__('Roles')">
                <x-slot:actions><a href="{{ route('church-admin.roles.create', $church) }}" class="btn btn-sm btn-ff-soft"><i class="bi bi-plus"></i> {{ __('Custom role') }}</a></x-slot:actions>
                <ul class="list-unstyled mb-0 d-grid gap-2">
                    <li class="ff-card ff-card-mist ff-card-sm">
                        <div class="fw-semibold text-ff-black">{{ __('Church admin') }}</div>
                        <div class="small">{{ __('Full access to everything.') }}</div>
                    </li>
                    @foreach ($roles as $role)
                        <li class="ff-card ff-card-gray ff-card-sm d-flex align-items-center gap-2">
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-ff-black">{{ $role->name }} @unless ($role->is_preset)<span class="ff-tag ms-1">{{ __('Custom') }}</span>@endunless</div>
                                <div class="small text-ff-muted">{{ trans_choice(':count permission|:count permissions', count($role->permissions ?? [])) }} · {{ trans_choice(':count person|:count people', $role->staff_count) }}</div>
                            </div>
                            <a href="{{ route('church-admin.roles.edit', [$church, $role]) }}" class="btn btn-sm btn-ff-ghost" aria-label="{{ __('Edit :role', ['role' => $role->name]) }}"><i class="bi bi-pencil"></i></a>
                        </li>
                    @endforeach
                </ul>
            </x-ff.card>
        </div>
    </div>

    {{-- Add staff modal --}}
    <x-ff.modal id="inviteStaff" :title="__('Add a staff member')">
        <form method="POST" action="{{ route('church-admin.staff.store', $church) }}" id="inviteStaffForm" x-data="{ access: '{{ old('access', 'role') }}' }" novalidate>
            @csrf
            <p class="small text-ff-muted">{{ __('They will receive an email. New people get a link to set their password.') }}</p>
            <x-ff.input name="name" :label="__('Full name')" required />
            <x-ff.input name="email" type="email" :label="__('Email')" required />
            <x-ff.input name="title" :label="__('Title (optional)')" :placeholder="__('e.g. Treasurer, Youth Pastor')" />
            @include('church-admin.roles._access-fields', ['member' => null])
        </form>
        <x-slot:footer>
            <button type="submit" form="inviteStaffForm" class="btn btn-ff-primary px-4">{{ __('Add & notify') }}</button>
        </x-slot:footer>
    </x-ff.modal>

    @foreach ($staff as $member)
        <x-ff.modal :id="'editStaff'.$member->id" :title="__('Edit :name', ['name' => $member->user?->name])">
            <form method="POST" action="{{ route('church-admin.staff.update', [$church, $member]) }}" id="editStaffForm{{ $member->id }}" x-data="{ access: '{{ $member->is_admin ? 'admin' : 'role' }}' }" novalidate>
                @csrf @method('PUT')
                <x-ff.input name="title" :label="__('Title')" :value="$member->title" :id="'title_'.$member->id" />
                @include('church-admin.roles._access-fields', ['member' => $member])
            </form>
            <x-slot:footer>
                <button type="submit" form="editStaffForm{{ $member->id }}" class="btn btn-ff-primary px-4">{{ __('Save') }}</button>
            </x-slot:footer>
        </x-ff.modal>
    @endforeach

    @if ($errors->any() && old('email'))
        @push('scripts')
            <script type="module">document.addEventListener('DOMContentLoaded', () => window.bootstrap?.Modal.getOrCreateInstance('#inviteStaff').show());</script>
        @endpush
    @endif
</x-layouts.church-admin>
