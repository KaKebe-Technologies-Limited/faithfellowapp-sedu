<x-layouts.member :title="__('Share a prayer request')">
    <x-ff.page-header :title="__('Share a prayer request')" :breadcrumbs="[__('Prayer wall') => route('prayer.index'), __('New request') => null]" />

    @if ($churches->isEmpty())
        <x-ff.empty-state icon="bi-bookmark-heart" :title="__('Follow a church first')" :message="__('Prayer requests are shared with a church family. Subscribe to your church to post one.')">
            <a href="{{ route('churches.index') }}" class="btn btn-ff-primary mt-2">{{ __('Find your church') }}</a>
        </x-ff.empty-state>
    @else
        <form method="POST" action="{{ route('prayer.store') }}" class="ff-card" style="max-width: 720px" x-data="{ visibility: @js(old('visibility', 'church')) }">
            @csrf
            <x-ff.select name="church_id" :label="__('Share with')" :options="$churches" :value="$selected ?? $churches->keys()->first()" required />
            <x-ff.input name="title" :label="__('Title (optional)')" maxlength="160" :placeholder="__('e.g. Healing for my mother')" />
            <x-ff.textarea name="body" :label="__('Your request')" rows="5" maxlength="2000" required :placeholder="__('Share as much or as little as you are comfortable with.')" />

            <fieldset class="mb-3">
                <legend class="form-label">{{ __('Who can see it?') }}</legend>
                @foreach (['church' => [__('My church family'), __('A pastor approves it first, then every member is invited to stand in prayer with you.'), 'bi-people'], 'public' => [__('Everyone'), __('Approved first, then also shown on the church’s public page.'), 'bi-globe2'], 'pastor' => [__('Pastors only'), __('Private — only the pastoral team sees it and can reply to you.'), 'bi-lock']] as $value => [$label, $hint, $icon])
                    <label class="d-flex gap-2 align-items-start p-2 rounded-3 border mb-2" :class="visibility === '{{ $value }}' && 'border-primary'">
                        <input type="radio" name="visibility" value="{{ $value }}" class="form-check-input mt-1" x-model="visibility" @checked(old('visibility', 'church') === $value)>
                        <span><i class="bi {{ $icon }}"></i> <strong class="text-ff-black">{{ $label }}</strong><span class="d-block small text-ff-muted">{{ $hint }}</span></span>
                    </label>
                @endforeach
            </fieldset>

            <x-ff.checkbox name="is_anonymous" :label="__('Post anonymously')" :help="__('Your name is hidden from members. Pastors can still follow up with you.')" switch />
            <div x-show="visibility !== 'pastor'">
                <x-ff.checkbox name="chain_enabled" :label="__('Start a prayer chain')" :help="__('We will ask church members one by one to pray, each passing it on to the next.')" switch />
            </div>

            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-ff-primary"><i class="bi bi-send"></i> {{ __('Share request') }}</button>
                <a href="{{ route('prayer.index') }}" class="btn btn-ff-ghost">{{ __('Cancel') }}</a>
            </div>
        </form>
    @endif
</x-layouts.member>
