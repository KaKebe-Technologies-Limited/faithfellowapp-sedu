@php($layout = auth()->check() ? 'layouts.member' : 'layouts.public')
<x-dynamic-component :component="$layout" :title="$stream->title.' · '.__('Live')">
    <div @class(['container py-4' => ! auth()->check()])>
        <div class="row g-4">
            <div class="col-xl-8">
                @if ($stream->isLive())
                    <div x-data="livePlayer({ src: @js($playback['video']), audioSrc: @js($playback['audio']), heartbeatUrl: @js(route('live.heartbeat', $stream)), viewers: {{ $viewers }}, audioOnly: {{ ($ffLite ?? false) ? 'true' : 'false' }} })">
                        <div class="ff-live-player mb-3">
                            <div class="ff-live-overlay">
                                <span class="ff-badge-live">{{ __('Live') }}</span>
                                <span class="ff-live-viewers"><i class="bi bi-eye"></i> <span x-text="viewers">{{ $viewers }}</span></span>
                            </div>
                            @if ($playback['embed'])
                                <iframe src="{{ $playback['embed'] }}" title="{{ $stream->title }}" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>
                            @else
                                <video x-ref="video" x-show="!audioOnly" controls autoplay playsinline muted></video>
                                <div class="ff-live-audio-only" x-show="audioOnly" x-cloak>
                                    <div class="ff-eq"><span></span><span></span><span></span><span></span><span></span></div>
                                    <div class="fw-bold fs-5">{{ __('Audio-only mode') }}</div>
                                    <div class="small" style="opacity:.85">{{ __('Uses about a tenth of the data.') }}</div>
                                    <audio x-ref="audio" controls autoplay></audio>
                                </div>
                            @endif
                        </div>
                        @if (! $playback['embed'] && $stream->audio_only_available)
                            <div class="d-flex gap-2 mb-3">
                                <button type="button" class="btn btn-sm" :class="audioOnly ? 'btn-ff-outline' : 'btn-ff-primary'" @click="audioOnly = false"><i class="bi bi-camera-video"></i> {{ __('Video') }}</button>
                                <button type="button" class="btn btn-sm" :class="audioOnly ? 'btn-ff-primary' : 'btn-ff-outline'" @click="audioOnly = true"><i class="bi bi-headphones"></i> {{ __('Audio only (save data)') }}</button>
                            </div>
                        @endif
                    </div>
                @elseif ($stream->status === 'scheduled')
                    <div class="ff-live-player mb-3">
                        <div class="ff-live-audio-only">
                            <i class="bi bi-broadcast fs-1"></i>
                            <div class="fw-bold fs-4">{{ __('Starts :date', ['date' => $stream->scheduled_at?->format('l, g:i A')]) }}</div>
                            <div class="fs-5" x-data="countdown('{{ $stream->scheduled_at?->toIso8601String() }}')" x-text="left"></div>
                            <div class="small" style="opacity:.85">{{ __('Subscribers get a notification the moment we go live.') }}</div>
                        </div>
                    </div>
                @else
                    <div class="ff-card text-center p-5 mb-3">
                        <i class="bi bi-broadcast fs-1 text-ff-muted"></i>
                        <h2 class="h5 mt-2">{{ __('This service has ended') }}</h2>
                        @if ($stream->sermon)
                            <a href="{{ route('sermons.show', $stream->sermon) }}" class="btn btn-ff-primary mt-2"><i class="bi bi-play-fill"></i> {{ __('Watch the recording') }}</a>
                        @else
                            <p class="text-ff-muted mb-0">{{ __('The recording will be published soon.') }}</p>
                        @endif
                    </div>
                @endif

                <div class="d-flex align-items-center gap-3 mb-2">
                    <x-ff.church-avatar :church="$stream->church" />
                    <div>
                        <h1 class="h4 mb-0">{{ $stream->title }}</h1>
                        <a href="{{ route('churches.show', $stream->church) }}" class="small text-ff-muted">{{ $stream->church->name }}</a>
                    </div>
                </div>
                @if ($stream->description)<p class="text-ff-muted">{{ $stream->description }}</p>@endif
                @if (Route::has('give.create'))
                    <a href="{{ route('give.create', $stream->church) }}" class="btn btn-ff-outline btn-sm"><i class="bi bi-heart"></i> {{ __('Give offering') }}</a>
                @endif
            </div>

            <aside class="col-xl-4">
                @if ($stream->chat_enabled && $stream->isLive())
                    <div class="ff-card ff-card-flush ff-chat"
                         x-data="liveChat({ fetchUrl: @js(route('live.messages', $stream)), postUrl: @js(auth()->check() ? route('live.messages.store', $stream) : ''), hideUrl: @js($canModerate ? route('church-admin.live.messages.hide', [$stream->church, $stream, '__ID__']) : '') })">
                        <div class="px-3 py-2 border-bottom fw-semibold text-ff-black"><i class="bi bi-chat-dots"></i> {{ __('Live chat') }}</div>
                        <div class="ff-chat-messages" x-ref="box" aria-live="polite">
                            <template x-for="m in messages" :key="m.id">
                                <div class="ff-chat-message d-flex gap-2">
                                    <div class="flex-grow-1"><strong x-text="m.name"></strong><span x-text="m.body"></span></div>
                                    @if ($canModerate)<button type="button" class="btn btn-sm btn-link text-danger p-0" @click="hide(m.id)" aria-label="{{ __('Hide') }}"><i class="bi bi-eye-slash"></i></button>@endif
                                </div>
                            </template>
                            <p class="small text-ff-muted text-center my-4" x-show="messages.length === 0">{{ __('Say hello to the church family 👋') }}</p>
                        </div>
                        @auth
                            <form class="d-flex gap-2 p-2 border-top" @submit.prevent="send()">
                                <input x-model="body" class="form-control form-control-sm" maxlength="500" placeholder="{{ __('Type a message…') }}" aria-label="{{ __('Message') }}">
                                <button class="btn btn-sm btn-ff-primary" :disabled="sending" aria-label="{{ __('Send') }}"><i class="bi bi-send"></i></button>
                            </form>
                        @else
                            <div class="p-2 border-top small text-center"><a href="{{ route('login') }}">{{ __('Sign in to chat') }}</a></div>
                        @endauth
                    </div>
                @else
                    <div class="ff-card ff-card-mist">
                        <h2 class="h6">{{ __('Watching on a phone?') }}</h2>
                        <p class="small mb-0">{{ __('Switch to audio-only to save up to 90% of your data. Turn on data-saver mode to make it the default.') }}</p>
                    </div>
                @endif
            </aside>
        </div>
    </div>
    @include('partials.note-taker', ['context' => 'live:'.$stream->id, 'reference' => $stream->title, 'churchId' => $stream->church_id])
</x-dynamic-component>
