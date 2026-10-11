{{-- Member church switcher: filter the feed by church, jump to a church page. --}}
@php($activeSlug = request()->routeIs('dashboard') ? request('church') : null)
<div class="dropdown ff-church-switcher">
    <button class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Switch church') }}">
        @php($active = $activeSlug ? $subscribedChurches->firstWhere('slug', $activeSlug) : null)
        @if ($active)
            <x-ff.church-avatar :church="$active" size="sm" />
            <span class="min-w-0"><span class="d-block fw-semibold text-ff-black text-truncate small">{{ $active->name }}</span><span class="d-block small-xs text-ff-muted">{{ __('Filtering feed') }}</span></span>
        @else
            <span class="ff-avatar ff-avatar-sm ff-avatar-square"><i class="bi bi-collection"></i></span>
            <span class="min-w-0"><span class="d-block fw-semibold text-ff-black small">{{ __('All my churches') }}</span><span class="d-block small-xs text-ff-muted">{{ trans_choice(':count church|:count churches', $subscribedChurches->count()) }}</span></span>
        @endif
    </button>
    <ul class="dropdown-menu">
        <li><a @class(['dropdown-item', 'active' => ! $active]) href="{{ route('dashboard') }}"><i class="bi bi-collection me-2"></i>{{ __('All my churches') }}</a></li>
        @foreach ($subscribedChurches as $c)
            <li>
                <a @class(['dropdown-item d-flex align-items-center gap-2', 'active' => $active?->id === $c->id]) href="{{ route('dashboard', ['church' => $c->slug]) }}">
                    <x-ff.church-avatar :church="$c" size="xs" /> <span class="text-truncate">{{ $c->name }}</span>
                </a>
            </li>
        @endforeach
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="{{ route('churches.index') }}"><i class="bi bi-plus-circle me-2"></i>{{ __('Find more churches') }}</a></li>
    </ul>
</div>
