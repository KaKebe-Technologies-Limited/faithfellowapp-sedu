@php($layout = auth()->check() ? 'layouts.member' : 'layouts.public')
<x-dynamic-component :component="$layout" :title="$announcement->title">
    <article @class(['container py-5' => ! auth()->check()]) style="max-width: 820px">
        <div class="d-flex align-items-center gap-2 mb-3">
            <x-ff.church-avatar :church="$announcement->church" size="sm" />
            <a href="{{ route('churches.show', $announcement->church) }}" class="fw-semibold text-ff-black">{{ $announcement->church->name }}</a>
            <span class="small text-ff-muted">· {{ $announcement->published_at?->format('j F Y, H:i') }}</span>
            @if ($announcement->priority === 'urgent')<span class="ff-badge ff-badge-danger ms-auto">{{ __('Urgent') }}</span>@endif
        </div>
        <h1 class="h2 mb-4">{{ $announcement->title }}</h1>
        @if ($announcement->image_url)<img src="{{ $announcement->image_url }}" alt="" class="ff-cover ff-rounded-xl mb-4">@endif
        <div class="ff-prose">{!! ff_safe_html($announcement->body) !!}</div>
        @auth
            <div class="mt-4">@include('partials.engagement-bar', ['model' => $announcement, 'type' => 'announcement'])</div>
        @endauth
        @if ($announcement->expires_at)
            <p class="small text-ff-muted mt-4">{{ __('Valid until :date', ['date' => $announcement->expires_at->format('j M Y')]) }}</p>
        @endif
    </article>
</x-dynamic-component>
