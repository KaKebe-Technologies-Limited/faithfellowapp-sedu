<x-layouts.church-admin :church="$church" :title="__('Volunteers')">
    <x-ff.page-header :title="__('Volunteers')" :subtitle="__('Serving teams and the Sunday rota. Volunteers are notified and confirm from their phone.')">
        <button class="btn btn-ff-primary" data-bs-toggle="modal" data-bs-target="#team-modal"><i class="bi bi-plus-lg"></i> {{ __('New team') }}</button>
    </x-ff.page-header>

    @if ($declined->isNotEmpty())
        <div class="alert alert-warning d-flex gap-2 align-items-start">
            <i class="bi bi-exclamation-triangle"></i>
            <div>
                <strong>{{ __('Need cover:') }}</strong>
                {{ $declined->map(fn ($r) => $r->user->name.' ('.$r->team->name.', '.$r->service_date->format('j M').')')->implode('; ') }}
            </div>
        </div>
    @endif

    {{-- Rota grid: teams × next five Sundays --}}
    <x-ff.card :title="__('Rota')" flush class="mb-4">
        <x-slot:actions>
            <a href="{{ route('church-admin.volunteers.index', [$church, 'from' => $from->copy()->subWeeks(5)->toDateString()]) }}" class="btn btn-sm btn-ff-ghost" aria-label="{{ __('Earlier') }}"><i class="bi bi-chevron-left"></i></a>
            <a href="{{ route('church-admin.volunteers.index', $church) }}" class="btn btn-sm btn-ff-soft">{{ __('This week') }}</a>
            <a href="{{ route('church-admin.volunteers.index', [$church, 'from' => $from->copy()->addWeeks(5)->toDateString()]) }}" class="btn btn-sm btn-ff-ghost" aria-label="{{ __('Later') }}"><i class="bi bi-chevron-right"></i></a>
        </x-slot:actions>
        <div class="table-responsive">
            <table class="table ff-table ff-rota-table mb-0">
                <thead><tr><th>{{ __('Team') }}</th>@foreach ($dates as $d)<th>{{ $d->format('D j M') }}</th>@endforeach</tr></thead>
                <tbody>
                    @forelse ($teams as $team)
                        <tr>
                            <td><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:{{ $team->color }}"></span><strong class="text-ff-black">{{ $team->name }}</strong></td>
                            @foreach ($dates as $d)
                                <td>
                                    @foreach ($rotas[$team->id.'|'.$d->toDateString()] ?? [] as $r)
                                        <div @class(['ff-rota-chip', 'is-confirmed' => $r->status === 'confirmed', 'is-declined' => $r->status === 'declined'])>
                                            <span class="text-truncate">{{ $r->user->name }}@if ($r->role) · {{ $r->role }}@endif</span>
                                            @if ($r->status === 'confirmed')<i class="bi bi-check-circle-fill text-success" title="{{ __('Confirmed') }}"></i>@endif
                                            <form method="POST" action="{{ route('church-admin.volunteers.rota.destroy', [$church, $r]) }}" class="ms-auto">@csrf @method('DELETE')
                                                <button class="btn btn-link p-0 text-ff-muted" style="font-size:.7rem" aria-label="{{ __('Remove') }}"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        </div>
                                    @endforeach
                                    @if ($d->gte(today()->startOfDay()))
                                        <button type="button" class="btn btn-link btn-sm p-0 small" data-bs-toggle="modal" data-bs-target="#rota-modal"
                                            onclick="document.getElementById('rota_team').value='{{ $team->id }}';document.getElementById('rota_date').value='{{ $d->toDateString() }}'">
                                            <i class="bi bi-plus"></i> {{ __('Assign') }}
                                        </button>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ $dates->count() + 1 }}"><x-ff.empty-state icon="bi-hand-thumbs-up" :title="__('No teams yet')" :message="__('Start with ushers, choir, media or children’s church.')" class="border-0" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ff.card>

    <div class="row g-3">
        @foreach ($teams as $team)
            <div class="col-md-6 col-xl-4">
                <div class="ff-card h-100" style="border-top: 4px solid {{ $team->color }}">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <div class="flex-grow-1">
                            <h2 class="h6 mb-0">{{ $team->name }}</h2>
                            <div class="small text-ff-muted">{{ trans_choice(':count volunteer|:count volunteers', $team->members_count) }}@if ($team->leader) · {{ __('Lead: :name', ['name' => $team->leader->name]) }}@endif</div>
                        </div>
                        <form method="POST" action="{{ route('church-admin.volunteers.teams.destroy', [$church, $team]) }}" data-ff-confirm="{{ __('Delete this team and its rota?') }}">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Delete team') }}"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                    @if ($team->description)<p class="small">{{ $team->description }}</p>@endif
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        @foreach ($team->members as $m)
                            <span class="ff-tag d-inline-flex align-items-center gap-1">{{ $m->name }}
                                <form method="POST" action="{{ route('church-admin.volunteers.members.destroy', [$church, $team, $m->id]) }}" class="d-inline">@csrf @method('DELETE')
                                    <button class="btn btn-link p-0 text-ff-muted" style="font-size:.65rem" aria-label="{{ __('Remove :name', ['name' => $m->name]) }}"><i class="bi bi-x-lg"></i></button>
                                </form>
                            </span>
                        @endforeach
                    </div>
                    <form method="POST" action="{{ route('church-admin.volunteers.members.store', [$church, $team]) }}" class="d-flex gap-2">@csrf
                        <select name="user_id" class="form-select form-select-sm" required aria-label="{{ __('Add volunteer') }}">
                            <option value="">{{ __('Add a volunteer…') }}</option>
                            @foreach ($people as $id => $name)@unless ($team->members->contains('id', $id))<option value="{{ $id }}">{{ $name }}</option>@endunless @endforeach
                        </select>
                        <button class="btn btn-sm btn-ff-soft">{{ __('Add') }}</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    <x-ff.modal id="team-modal" :title="__('New team')">
        <form method="POST" action="{{ route('church-admin.volunteers.teams.store', $church) }}" id="team-form">@csrf
            <x-ff.input name="name" :label="__('Team name')" required maxlength="120" :placeholder="__('e.g. Ushers')" />
            <x-ff.textarea name="description" :label="__('Description')" rows="2" maxlength="1000" />
            <x-ff.select name="leader_id" :label="__('Team leader')" :options="$people" :placeholder="__('— Optional —')" />
            <x-ff.input type="color" name="color" :label="__('Colour')" value="#BC2649" class="form-control-color" />
        </form>
        <x-slot:footer><button form="team-form" class="btn btn-ff-primary">{{ __('Create team') }}</button></x-slot:footer>
    </x-ff.modal>

    <x-ff.modal id="rota-modal" :title="__('Assign to rota')">
        <form method="POST" action="{{ route('church-admin.volunteers.rota.store', $church) }}" id="rota-form">@csrf
            <x-ff.select name="volunteer_team_id" id="rota_team" :label="__('Team')" :options="$teams->pluck('name', 'id')" required />
            <x-ff.input type="date" name="service_date" id="rota_date" :label="__('Date')" min="{{ today()->toDateString() }}" required />
            <x-ff.select name="user_id" :label="__('Volunteer')" :options="$people" :placeholder="__('— Choose —')" required />
            <x-ff.input name="role" :label="__('Role (optional)')" maxlength="80" :placeholder="__('e.g. Door, Sound desk')" />
        </form>
        <x-slot:footer><button form="rota-form" class="btn btn-ff-primary">{{ __('Assign & notify') }}</button></x-slot:footer>
    </x-ff.modal>
</x-layouts.church-admin>
