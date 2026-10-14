<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $podcast->name }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #1a1a1a; background: #fff; }
        .wrap { padding: 14px; }
        header { display: flex; gap: 12px; align-items: center; margin-bottom: 12px; }
        header img { width: 64px; height: 64px; border-radius: 12px; object-fit: cover; flex-shrink: 0; background: #f1f1f1; }
        header a { color: inherit; text-decoration: none; }
        h1 { font-size: 17px; margin: 0; line-height: 1.25; }
        .by { font-size: 13px; color: #727272; margin-top: 2px; }
        .ep { border-top: 1px solid #eee; padding: 10px 0; }
        .ep b { display: block; font-size: 14px; margin-bottom: 2px; }
        .ep small { color: #727272; font-size: 12px; }
        audio { width: 100%; margin-top: 6px; height: 36px; }
        footer { border-top: 1px solid #eee; padding-top: 10px; font-size: 12px; color: #727272; }
        footer a { color: #bc2649; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
<div class="wrap">
    <header>
        <img src="{{ $podcast->coverUrl() }}" alt="">
        <div>
            <h1><a href="{{ route('podcasts.listen.show', $podcast->slug) }}" target="_blank" rel="noopener">{{ $podcast->name }}</a></h1>
            <div class="by">{{ __('Presented by :name', ['name' => $podcast->host_name]) }}</div>
        </div>
    </header>

    @forelse ($episodes as $episode)
        <div class="ep">
            <b>{{ $episode->title }}</b>
            <small>{{ $episode->published_at?->format('j M Y') }}@if ($episode->durationLabel()) · {{ $episode->durationLabel() }}@endif</small>
            <audio controls preload="none" src="{{ $episode->audio_url }}" aria-label="{{ $episode->title }}"></audio>
        </div>
    @empty
        <p class="by">{{ __('No episodes yet.') }}</p>
    @endforelse

    <footer>{{ __('More on') }} <a href="{{ route('podcasts.listen.show', $podcast->slug) }}" target="_blank" rel="noopener">FaithFellows</a></footer>
</div>
</body>
</html>
