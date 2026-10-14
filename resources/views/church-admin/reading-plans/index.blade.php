<x-layouts.church-admin :church="$church" :title="__('Reading plans')">
    <x-ff.page-header :title="__('Bible reading plans')" :subtitle="__('Members track progress (“Day 47 of 365”), build streaks and get daily reminders.')">
        <a href="{{ route('church-admin.reading-plans.create', $church) }}" class="btn btn-ff-primary"><i class="bi bi-plus-lg"></i> {{ __('Custom plan') }}</a>
    </x-ff.page-header>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="ff-table-wrap">
                <div class="table-responsive">
                    <table class="table ff-table">
                        <thead><tr><th>{{ __('Plan') }}</th><th>{{ __('Days') }}</th><th>{{ __('Readers') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($plans as $plan)
                                <tr>
                                    <td class="fw-semibold text-ff-black">{{ $plan->title }}</td>
                                    <td>{{ $plan->days_count }}</td>
                                    <td>{{ $plan->progress_count }}</td>
                                    <td><x-ff.status :status="$plan->is_published ? 'published' : 'draft'" /></td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('church-admin.reading-plans.edit', [$church, $plan]) }}" class="btn btn-sm btn-ff-soft">{{ __('Edit') }}</a>
                                        <form method="POST" action="{{ route('church-admin.reading-plans.destroy', [$church, $plan]) }}" class="d-inline" data-ff-confirm="{{ __('Delete this plan and all reader progress?') }}">@csrf @method('DELETE')
                                            <button class="btn btn-sm btn-ff-ghost text-danger" aria-label="{{ __('Delete') }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5"><x-ff.empty-state icon="bi-journal-check" :title="__('No plans yet')" :message="__('Start with a ready-made plan on the right.')" class="border-0" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <x-ff.card :title="__('Ready-made plans')">
                <div class="d-grid gap-2">
                    @foreach ($templates as $key => $template)
                        <form method="POST" action="{{ route('church-admin.reading-plans.import', $church) }}" class="ff-card ff-card-gray ff-card-sm d-flex align-items-center gap-2">@csrf
                            <input type="hidden" name="template" value="{{ $key }}">
                            <div class="flex-grow-1"><div class="fw-semibold text-ff-black small">{{ $template['title'] }}</div><div class="small-xs text-ff-muted">{{ count($template['entries']) }} {{ __('days') }}</div></div>
                            <button class="btn btn-sm btn-ff-primary">{{ __('Add') }}</button>
                        </form>
                    @endforeach
                </div>
            </x-ff.card>
        </div>
    </div>
</x-layouts.church-admin>
