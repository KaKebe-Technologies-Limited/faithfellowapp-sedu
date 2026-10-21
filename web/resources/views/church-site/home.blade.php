<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-ff.head :title="$church->name" :description="$church->description ?: $church->tagline" :image="$church->cover_url" />
    <style>
        :root { --site-accent: {{ $church->brand_color ?: '#BC2649' }}; }
        .site-accent { color: var(--site-accent) !important; }
        .site-btn { --bs-btn-bg: var(--site-accent); --bs-btn-border-color: var(--site-accent); --bs-btn-color: #fff; --bs-btn-hover-bg: var(--site-accent); --bs-btn-hover-color: #fff; --bs-btn-hover-border-color: var(--site-accent); border-radius: 9999px; filter: saturate(1.05); }
        .site-btn:hover { filter: brightness(.92); }
        .site-hero { position: relative; color: #fff; padding: clamp(80px, 14vw, 160px) 0 clamp(64px, 10vw, 120px); background: linear-gradient(135deg, var(--site-accent), #F27EA8); overflow: hidden; }
        .site-hero.has-cover { background: linear-gradient(180deg, rgba(0,0,0,.25), rgba(0,0,0,.65)), var(--site-cover) center/cover; }
        /* No banner photo yet: the FaithFellows mark, big and faint, behind the heading. */
        .site-hero:not(.has-cover) { isolation: isolate; }
        .site-hero:not(.has-cover)::after { content: ''; z-index: -1; position: absolute; right: -6%; top: 50%; width: min(60vw, 560px); aspect-ratio: 1; transform: translateY(-50%); opacity: .1; pointer-events: none; background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100' fill='none'%3E%3Crect x='5.1' y='5.1' width='89.8' height='89.8' rx='30.4' stroke='white' stroke-width='10.2'/%3E%3Cg stroke='white' stroke-width='10.2'%3E%3Cpath d='M22.3 51.6H55.6M50.5 46.5V78.5'/%3E%3Cpath d='M49.7 21.7A29.9 29.9 0 0 0 79.6 51.6'/%3E%3C/g%3E%3C/svg%3E") center / contain no-repeat; }
        .site-hero h1 { color: #fff; }
        .site-nav { position: absolute; inset: 0 0 auto 0; z-index: 2; }
        .site-section:nth-of-type(even) { background: var(--ff-gray-100); }
    </style>
</head>
<body @class(['ff-lite' => $ffLite ?? false])>
    <a href="#main" class="ff-skip-link">{{ __('Skip to content') }}</a>

    <header class="site-nav">
        <nav class="container d-flex align-items-center gap-3 py-3" aria-label="{{ __('Church navigation') }}">
            <a href="{{ ff_church_url($church) }}" class="d-flex align-items-center gap-2 text-white fw-bold text-decoration-none">
                <x-ff.church-avatar :church="$church" size="sm" />
                <span>{{ $church->name }}</span>
            </a>
            <div class="ms-auto d-flex gap-2">
                <a href="{{ ff_platform_route('churches.show', $church) }}" class="btn btn-sm btn-ff-white d-none d-sm-inline-flex">{{ __('Follow on FaithFellows') }}</a>
                @if (Route::has('give.create'))
                    <a href="{{ ff_platform_route('give.create', $church) }}" class="btn btn-sm site-btn">{{ __('Give') }}</a>
                @endif
            </div>
        </nav>
    </header>

    <main id="main">
        @foreach ($blocks as $block)
            @includeIf('church-site.blocks.'.$block)
        @endforeach
    </main>

    <footer class="ff-footer py-5">
        <div class="container d-flex flex-column flex-md-row justify-content-between gap-3 small">
            <span>&copy; {{ date('Y') }} {{ $church->name }}</span>
            <span>{{ __('Powered by') }} <a href="{{ ff_platform_route('home') }}" class="text-white fw-semibold">FaithFellows</a></span>
        </div>
    </footer>
    @include('partials.flash')
</body>
</html>
