<x-layouts.member :title="__('Serving')">
    <x-ff.page-header :title="__('Serving')" :subtitle="__('Your upcoming volunteer rota. Please confirm so your team can plan.')" />

    @forelse ($rotas->groupBy(fn ($r) => $r->service_date->toDateString()) as $date => $items)
        <h2 class="h6 text-uppercase text-ff-muted mt-4 mb-2">{{ \Illuminate\Support\Carbon::parse($date)->format('l, j F') }}</h2>
        @foreach ($items as $rota)
            <div class="ff-card ff-card-sm d-flex flex-wrap align-items-center gap-3 mb-2" style="border-left: 4px solid {{ $rota->team->color }}">
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-ff-black">{{ $rota->team->name }}@if ($rota->role) · {{ $rota->role }}@endif</div>
                    <div class="small text-ff-muted">{{ $rota->church->name }}</div>
                </div>
                <x-ff.status :status="$rota->status" />
                <form method="POST" action="{{ route('volunteering.respond', $rota) }}" class="d-flex gap-2">@csrf @method('PUT')
                    @if ($rota->status !== 'confirmed')<button name="status" value="confirmed" class="btn btn-sm btn-ff-primary">{{ __('Confirm') }}</button>@endif
                    @if ($rota->status !== 'declined')<button name="status" value="declined" class="btn btn-sm btn-ff-ghost">{{ __('Can’t make it') }}</button>@endif
                </form>
            </div>
        @endforeach
    @empty
        <x-ff.empty-state icon="bi-hand-thumbs-up" :title="__('You are not on the rota')" :message="__('Ask your church about joining a serving team — ushering, choir, media, children’s church and more.')" />
    @endforelse
</x-layouts.member>
