<x-layouts.church-admin :church="$church" :title="__('Integrations')">
    @php($i = $church->setting('integrations', []))
    <x-ff.page-header :title="__('Settings')" />
    @include('church-admin.settings._nav')

    <form method="POST" action="{{ route('church-admin.settings.integrations.update', $church) }}" class="row g-4" novalidate>
        @csrf
        @method('PUT')
        <div class="col-lg-7">
            <div class="ff-card">
                <div class="ff-form-section">
                    <h3>{{ __('Social media & auto-share') }}</h3>
                    <p class="small text-ff-muted">{{ __('New sermons, events and campaigns get one-tap share links for these channels. With auto-share on, our media team posts highlights twice a week.') }}</p>
                    <x-ff.input name="facebook_page" type="url" :label="__('Facebook page')" :value="$i['facebook_page'] ?? null" placeholder="https://facebook.com/yourchurch" />
                    <x-ff.input name="youtube_channel" type="url" :label="__('YouTube channel')" :value="$i['youtube_channel'] ?? null" placeholder="https://youtube.com/@yourchurch" />
                    <x-ff.input name="whatsapp_group" type="url" :label="__('WhatsApp channel / group link')" :value="$i['whatsapp_group'] ?? null" placeholder="https://chat.whatsapp.com/…" />
                    <x-ff.checkbox name="auto_share" :label="__('Include us in the FaithFellows 2× weekly social posts')" :checked="$i['auto_share'] ?? true" switch />
                </div>
                <div class="ff-form-section">
                    <h3>{{ __('SMS & WhatsApp') }}</h3>
                    <x-ff.input name="sms_sender_id" :label="__('Preferred SMS sender ID')" :value="$i['sms_sender_id'] ?? null" maxlength="11" :help="__('Up to 11 letters, e.g. GRACECHURCH. Registration with networks is handled by FaithFellows.')" />
                    <x-ff.checkbox name="notify_sms_default" :label="__('Send important announcements by SMS by default')" :checked="$i['notify_sms_default'] ?? false" switch />
                    <x-ff.checkbox name="notify_whatsapp_default" :label="__('Send the daily verse via WhatsApp')" :checked="$i['notify_whatsapp_default'] ?? false" switch />
                </div>
                <button class="btn btn-ff-primary px-4">{{ __('Save integrations') }}</button>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="ff-card ff-card-gray">
                <h2 class="h6">{{ __('Platform services') }}</h2>
                <ul class="list-unstyled small d-grid gap-2 mb-0">
                    <li><i class="bi bi-check-circle-fill text-success"></i> {{ __('Mobile money & card payments — configure under Giving channels') }}</li>
                    <li><i class="bi bi-check-circle-fill text-success"></i> {{ __('Email delivery with branded templates') }}</li>
                    <li><i class="bi bi-check-circle-fill text-success"></i> {{ __('SMS & USSD via Africa’s Talking') }}</li>
                    <li><i class="bi bi-check-circle-fill text-success"></i> {{ __('Live streaming (RTMP / OBS / Larix)') }}</li>
                    <li><i class="bi bi-hourglass-split text-warning"></i> {{ __('Sermon AI transcription — coming soon') }}</li>
                </ul>
            </div>
        </div>
    </form>
</x-layouts.church-admin>
