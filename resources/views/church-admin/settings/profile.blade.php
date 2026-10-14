<x-layouts.church-admin :church="$church" :title="__('Church profile')">
    <x-ff.page-header :title="__('Settings')" :subtitle="__('Manage how :church appears across FaithFellows.', ['church' => $church->name])" />
    @include('church-admin.settings._nav')

    <form method="POST" action="{{ route('church-admin.settings.profile.update', $church) }}" class="row g-4" novalidate>
        @csrf
        @method('PUT')
        <div class="col-lg-8">
            <div class="ff-card">
                @include('partials.church-fields', ['church' => $church, 'showAbout' => true])
                <div class="ff-form-section">
                    <h3>{{ __('Social links') }}</h3>
                    <div class="row">
                        @foreach ($platforms as $platform)
                            @continue($platform === 'other')
                            <div class="col-md-6">
                                <x-ff.input :name="'social['.$platform.']'" type="url" :label="ucfirst($platform)" :value="$church->socialLinks->firstWhere('platform', $platform)?->url" placeholder="https://" />
                            </div>
                        @endforeach
                    </div>
                </div>
                <button class="btn btn-ff-primary px-4">{{ __('Save profile') }}</button>
            </div>
        </div>
        <div class="col-lg-4 d-grid gap-4 align-content-start">
            <div class="ff-card">
                <h2 class="h6">{{ __('Web address') }}</h2>
                <x-ff.input name="slug" :label="__('Church handle')" :value="$church->slug" :help="__('Lowercase letters, numbers and dashes. Changing this changes your links.')" />
                <div class="small text-ff-muted">
                    <div><i class="bi bi-globe"></i> {{ Str::after(ff_church_url($church), '://') }}</div>
                    <div><i class="bi bi-link-45deg"></i> {{ Str::after(route('churches.show', $church), '://') }}</div>
                </div>
            </div>
            <div class="ff-card">
                <h2 class="h6">{{ __('Membership') }}</h2>
                <x-ff.checkbox name="requires_member_approval" :label="__('Approve new subscribers before they see church-only content')" :checked="$church->requires_member_approval" switch />
            </div>
        </div>
    </form>
</x-layouts.church-admin>
