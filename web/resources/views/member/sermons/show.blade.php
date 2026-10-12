@php
    $layout = auth()->check() ? 'layouts.member' : 'layouts.public';
    $transcript = $sermon->transcripts->firstWhere('status', 'completed');
    $hasVideo = $sermon->video_embed_url || $sermon->video_file_src || ($sermon->video_url && ! $sermon->video_embed_url);
@endphp
<x-dynamic-component :component="$layout" :title="$sermon->title" :description="strip_tags((string) $sermon->description) ?: $sermon->title.' — '.$sermon->preacher" :image="$sermon->thumbnail_url">
    <div @class(['container py-4 py-md-5' => ! auth()->check()])>
        <div class="row g-4">
            <div class="col-xl-8">
                {{-- Player --}}
                @if ($hasVideo && ! ($ffLite ?? false))
                    <div class="ff-player mb-3">
                        @if ($sermon->video_embed_url)
                            <div class="ratio ratio-16x9"><iframe src="{{ $sermon->video_embed_url }}" title="{{ $sermon->title }}" allow="accelerometer; autoplay; encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe></div>
                        @else
                            <video controls preload="metadata" playsinline @if ($sermon->thumbnail_url) poster="{{ $sermon->thumbnail_url }}" @endif src="{{ $sermon->video_file_src ?: $sermon->video_url }}"></video>
                        @endif
                    </div>
                @endif

                @if ($sermon->audio_src)
                    <div class="ff-player mb-4" x-data="audioPlayer({ src: @js($sermon->audio_src), title: @js($sermon->title), church: @js($sermon->church->name), trackUrl: @js(route('sermons.track', $sermon)) })">
                        <div class="ff-player-audio">
                            <div class="d-flex align-items-center gap-3">
                                <x-ff.church-avatar :church="$sermon->church" size="lg" class="ff-avatar-ring" />
                                <div class="min-w-0">
                                    <div class="small text-white-50">{{ __('Now playing') }}</div>
                                    <div class="fw-bold text-white text-truncate">{{ $sermon->title }}</div>
                                </div>
                            </div>
                            <audio x-ref="audio" preload="none" src="{{ $sermon->audio_src }}"></audio>
                            <div class="ff-player-controls">
                                <button type="button" class="ff-player-play" @click="toggle()" :aria-label="playing ? '{{ __('Pause') }}' : '{{ __('Play') }}'">
                                    <i class="bi" :class="playing ? 'bi-pause-fill' : 'bi-play-fill'"></i>
                                </button>
                                <input type="range" class="ff-player-seek form-range" min="0" :max="duration || 0" step="1" :value="current" @input="seek($event)" aria-label="{{ __('Seek') }}">
                                <span class="ff-player-time" x-text="label">0:00 / 0:00</span>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="ff-player-btn" @click="skip(-15)"><i class="bi bi-arrow-counterclockwise"></i> 15s</button>
                                <button type="button" class="ff-player-btn" @click="skip(30)">30s <i class="bi bi-arrow-clockwise"></i></button>
                                <button type="button" class="ff-player-btn" @click="cycleRate()"><span x-text="rate + '×'">1×</span></button>
                                <button type="button" class="ff-player-btn ms-auto" x-show="!saved" @click="saveOffline()" :disabled="saving"><i class="bi bi-cloud-download"></i> <span x-text="saving ? '{{ __('Saving…') }}' : '{{ __('Save offline') }}'"></span></button>
                                <button type="button" class="ff-player-btn ms-auto" x-show="saved" x-cloak @click="removeOffline()"><i class="bi bi-check2-circle"></i> {{ __('Saved offline') }}</button>
                                <a href="{{ route('sermons.download', $sermon) }}" class="ff-player-btn text-decoration-none"><i class="bi bi-download"></i> {{ __('Download') }}</a>
                            </div>
                        </div>
                    </div>
                @elseif (! $hasVideo)
                    <x-ff.empty-state icon="bi-mic-mute" :title="__('Media coming soon')" :message="__('The recording for this sermon has not been uploaded yet.')" class="mb-4" />
                @endif

                <div class="d-flex flex-wrap gap-2 mb-2">
                    @if ($sermon->series)<span class="ff-tag">{{ $sermon->series->title }}</span>@endif
                    <span class="ff-badge">{{ config('faithfellows.languages.'.$sermon->language, $sermon->language) }}</span>
                    @if ($sermon->duration_label)<span class="ff-badge"><i class="bi bi-clock"></i> {{ $sermon->duration_label }}</span>@endif
                </div>
                <h1 class="h2">{{ $sermon->title }}</h1>
                <p class="text-ff-muted">
                    @if ($sermon->preacher)<i class="bi bi-person"></i> {{ $sermon->preacher }} · @endif
                    @if ($sermon->scripture)<a href="{{ auth()->check() ? route('bible.passage', ['ref' => $sermon->scripture]) : '#' }}" class="text-reset"><i class="bi bi-book"></i> {{ $sermon->scripture }}</a> · @endif
                    {{ ($sermon->preached_on ?? $sermon->published_at)?->format('j F Y') }}
                </p>

                <div class="d-flex flex-wrap gap-2 mb-4">
                    @if ($sermon->notes_url)
                        <a href="{{ $sermon->notes_url }}" target="_blank" class="btn btn-ff-soft btn-sm"><i class="bi bi-file-earmark-pdf"></i> {{ __('Sermon notes') }}</a>
                    @endif
                    @if ($sermon->scripture)
                        <button type="button" class="btn btn-ff-soft btn-sm" data-ff-verse-download data-verse="{{ Str::limit(strip_tags((string) $sermon->description) ?: $sermon->title, 180) }}" data-reference="{{ $sermon->scripture }}" data-church="{{ $sermon->church->name }}"><i class="bi bi-image"></i> {{ __('Share as card') }}</button>
                    @endif
                </div>

                @if ($sermon->description)
                    <div class="ff-card mb-4"><div class="ff-prose" style="font-size:1rem">{!! ff_safe_html($sermon->description) !!}</div></div>
                @endif

                @if ($transcript)
                    <details class="ff-card mb-4">
                        <summary class="fw-semibold text-ff-black">{{ __('Transcript') }} @if ($transcript->source === 'ai')<span class="ff-badge ff-badge-info ms-1">AI</span>@endif</summary>
                        @if ($transcript->summary)<p class="mt-3"><strong>{{ __('Summary') }}:</strong> {{ $transcript->summary }}</p>@endif
                        <div class="mt-3 small" style="white-space: pre-line; max-height: 480px; overflow-y: auto">{{ $transcript->body }}</div>
                    </details>
                @endif

                @includeIf('partials.engagement-bar', ['model' => $sermon, 'type' => 'sermon'])

                <div class="ff-card mt-4">
                    <h2 class="h6">{{ __('Share this sermon') }}</h2>
                    <x-ff.share :url="route('sermons.show', $sermon)" :title="$sermon->title.' — '.$sermon->church->name" />
                </div>
            </div>

            <aside class="col-xl-4 d-grid gap-4 align-content-start">
                <a href="{{ route('churches.show', $sermon->church) }}" class="ff-card ff-card-link d-flex align-items-center gap-3">
                    <x-ff.church-avatar :church="$sermon->church" size="lg" />
                    <div class="min-w-0"><div class="fw-bold text-ff-black text-truncate">{{ $sermon->church->name }}</div><div class="small text-ff-muted">{{ $sermon->church->location }}</div></div>
                </a>
                @if ($related->isNotEmpty())
                    <div>
                        <h2 class="h6 mb-3">{{ __('More from :church', ['church' => $sermon->church->name]) }}</h2>
                        <div class="d-grid gap-3">
                            @foreach ($related as $r)
                                <a href="{{ route('sermons.show', $r) }}" class="ff-card ff-card-sm ff-card-link d-flex gap-3 align-items-center">
                                    <span class="ff-avatar ff-avatar-square" style="--size:48px"><i class="bi bi-play-fill"></i></span>
                                    <span class="min-w-0"><span class="d-block fw-semibold text-ff-black text-truncate small">{{ $r->title }}</span><span class="small-xs text-ff-muted">{{ $r->preacher }} · {{ $r->published_at?->format('j M') }}</span></span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </aside>
        </div>
    </div>
    @include('partials.note-taker', ['context' => 'sermon:'.$sermon->id, 'reference' => $sermon->title, 'churchId' => $sermon->church_id])
</x-dynamic-component>
