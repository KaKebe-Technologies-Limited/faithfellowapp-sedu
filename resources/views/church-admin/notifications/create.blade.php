<x-layouts.church-admin :church="$church" :title="__('Send a notification')">
    <x-ff.page-header :title="__('Send a notification')" :breadcrumbs="[__('Notifications') => route('church-admin.notifications.index', $church), __('New') => null]" />

    <form method="POST" action="{{ route('church-admin.notifications.store', $church) }}" class="row g-4" novalidate data-ff-confirm="{{ __('Send this notification?') }}"
          x-data="{
              audience: @js(old('audience', 'members')),
              channels: @js(old('channels', ['mail', 'webpush'])),
              send: @js(old('send', 'now')),
              urgent: @js((bool) old('is_urgent')),
              title: @js(old('title', '')),
              body: @js(old('body', '')),
              est: @js($estimate),
              smsCost: {{ $smsCost }},
              timer: null,
              refresh() {
                  clearTimeout(this.timer);
                  this.timer = setTimeout(async () => {
                      const params = new URLSearchParams(new FormData(this.$root));
                      params.delete('_token');
                      const res = await fetch(@js(route('church-admin.notifications.estimate', $church)) + '?' + params.toString(), { headers: { Accept: 'application/json' } });
                      if (res.ok) this.est = await res.json();
                  }, 350);
              },
          }" @change="refresh()">
        @csrf
        <div class="col-lg-8 d-grid gap-4 align-content-start">
            <div class="ff-card">
                <x-ff.input name="title" :label="__('Title')" required maxlength="160" x-model="title" :placeholder="__('e.g. Service moved to 9 AM this Sunday')" />
                <x-ff.textarea name="body" :label="__('Message')" rows="5" required maxlength="2000" x-model="body" />
                <div class="small text-ff-muted mt-n2 mb-3"><span x-text="body.length"></span>/2000 · {{ __('SMS shows about the first 300 characters.') }}</div>
                <x-ff.input type="url" name="url" :label="__('Link (optional)')" :placeholder="route('churches.show', $church)" :help="__('Where people go when they tap the notification. Defaults to your church page.')" />
                <x-ff.select name="category" :label="__('Category')" :options="collect(config('faithfellows.notification_categories'))->map(fn ($l) => __($l))" value="announcement"
                    :help="__('Members choose which categories reach them by email, push or SMS.')" />
            </div>

            {{-- Live preview --}}
            <div class="ff-card ff-card-mist">
                <div class="small text-uppercase text-ff-muted fw-semibold mb-2">{{ __('Preview') }}</div>
                <div class="d-flex gap-3 align-items-start bg-white rounded-3 p-3 shadow-sm" style="max-width: 420px">
                    <x-ff.church-avatar :church="$church" size="sm" />
                    <div class="min-w-0">
                        <div class="small-xs text-ff-muted">{{ $church->name }} · {{ __('now') }}</div>
                        <div class="fw-semibold text-ff-black"><span x-show="urgent">⚠️ </span><span x-text="title || @js(__('Your title'))"></span></div>
                        <div class="small text-truncate" x-text="body || @js(__('Your message…'))"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 d-grid gap-4 align-content-start">
            <div class="ff-card">
                <h2 class="h6">{{ __('Who should receive it?') }}</h2>
                <select name="audience" class="form-select mb-2" x-model="audience" aria-label="{{ __('Audience') }}">
                    @foreach (\App\Models\ChurchBroadcast::AUDIENCES as $value => $label)
                        @if (($value !== 'branch' || $branches->isNotEmpty()) && ($value !== 'group' || $groups->isNotEmpty()))
                            <option value="{{ $value }}">{{ __($label) }}</option>
                        @endif
                    @endforeach
                </select>
                <div x-show="audience === 'branch'" x-cloak><x-ff.select name="branch_id" :options="$branches" :placeholder="__('— Choose branch —')" /></div>
                <div x-show="audience === 'group'" x-cloak><x-ff.select name="small_group_id" :options="$groups" :placeholder="__('— Choose group —')" /></div>
                <div x-show="audience === 'stage'" x-cloak class="mb-2">
                    @foreach (['visitor', 'new_member', 'member', 'worker', 'leader'] as $stage)
                        <x-ff.checkbox name="stages[]" :value="$stage" :label="__(Str::headline($stage))" />
                    @endforeach
                </div>
                <div class="ff-badge ff-badge-primary"><i class="bi bi-people"></i> <span x-text="est.recipients.toLocaleString()"></span> {{ __('people') }}</div>
            </div>

            <div class="ff-card">
                <h2 class="h6">{{ __('Channels') }}</h2>
                <p class="small text-ff-muted">{{ __('Everyone gets it in the app.') }} <span x-show="!urgent">{{ __('Other channels follow each member’s preferences.') }}</span></p>
                @foreach (['mail' => [__('Email'), 'bi-envelope'], 'webpush' => [__('Push notification'), 'bi-phone-vibrate'], 'sms' => [__('SMS'), 'bi-chat-left-text'], 'whatsapp' => [__('WhatsApp'), 'bi-whatsapp']] as $value => [$label, $icon])
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" name="channels[]" value="{{ $value }}" id="ch-{{ $value }}" x-model="channels">
                        <label class="form-check-label" for="ch-{{ $value }}"><i class="bi {{ $icon }}"></i> {{ $label }}</label>
                    </div>
                @endforeach
                <div class="small mt-2 p-2 rounded-3 bg-ff-mist" x-show="channels.includes('sms')" x-cloak>
                    <i class="bi bi-cash-coin"></i>
                    <span x-show="urgent">{{ __('Up to') }} <strong x-text="'UGX ' + Math.round(est.sms_cost).toLocaleString()"></strong> {{ __('for') }} <span x-text="est.with_phone.toLocaleString()"></span> {{ __('phones') }}.</span>
                    <span x-show="!urgent">{{ __('Only members who opted into SMS are texted — at most') }} <strong x-text="'UGX ' + Math.round(est.sms_cost).toLocaleString()"></strong>.</span>
                    @unless ($smsEnabled)<div class="text-warning-emphasis mt-1">{{ __('SMS is in test mode on this server — messages are logged, not sent.') }}</div>@endunless
                </div>
                <hr>
                <x-ff.checkbox name="is_urgent" :label="__('Urgent')" switch x-model="urgent"
                    :help="__('Sends on the ticked channels to everyone, even during quiet hours. Use sparingly.')" />
            </div>

            <div class="ff-card">
                <h2 class="h6">{{ __('When?') }}</h2>
                <div class="form-check"><input class="form-check-input" type="radio" name="send" value="now" id="send-now" x-model="send"><label class="form-check-label" for="send-now">{{ __('Send now') }}</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="radio" name="send" value="later" id="send-later" x-model="send"><label class="form-check-label" for="send-later">{{ __('Schedule') }}</label></div>
                <div x-show="send === 'later'" x-cloak><x-ff.input type="datetime-local" name="scheduled_at" min="{{ now()->addMinutes(5)->format('Y-m-d\TH:i') }}" /></div>
                <button class="btn btn-ff-primary w-100 mt-2">
                    <i class="bi bi-send"></i> <span x-text="send === 'later' ? @js(__('Schedule')) : @js(__('Send now'))"></span>
                </button>
            </div>
        </div>
    </form>
</x-layouts.church-admin>
