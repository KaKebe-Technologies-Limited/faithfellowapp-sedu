@props(['title' => null, 'subtitle' => null, 'flush' => false])
<section {{ $attributes->class(['ff-card', 'ff-card-flush' => $flush]) }}>
    @if ($title || isset($actions))
        <header @class(['ff-card-header', 'px-4 pt-4' => $flush])>
            <div class="min-w-0">
                @if ($title)<h2>{{ $title }}</h2>@endif
                @if ($subtitle)<p class="small text-ff-muted mb-0">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="d-flex gap-2 flex-wrap">{{ $actions }}</div>@endisset
        </header>
    @endif
    {{ $slot }}
</section>
