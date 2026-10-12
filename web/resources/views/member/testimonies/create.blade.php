<x-layouts.member :title="__('Share your testimony')">
    <x-ff.page-header :title="__('Share your testimony')" :subtitle="__('Your church reviews testimonies before they appear, so you can write freely.')"
        :breadcrumbs="[__('Testimonies') => route('testimonies.index'), __('New') => null]" />

    @if ($churches->isEmpty())
        <x-ff.empty-state icon="bi-bookmark-heart" :title="__('Follow a church first')" :message="__('Testimonies are shared with a church family.')">
            <a href="{{ route('churches.index') }}" class="btn btn-ff-primary mt-2">{{ __('Find your church') }}</a>
        </x-ff.empty-state>
    @else
        <form method="POST" action="{{ route('testimonies.store') }}" class="ff-card" style="max-width: 720px">
            @csrf
            @if ($prayer)
                <input type="hidden" name="prayer_request_id" value="{{ $prayer->id }}">
                <div class="small p-3 rounded-3 mb-3" style="background: rgba(30,158,90,.08)">
                    <i class="bi bi-stars text-success"></i> {{ __('Answered prayer:') }} <em>{{ Str::limit($prayer->body, 140) }}</em>
                </div>
            @endif
            <x-ff.select name="church_id" :label="__('Church')" :options="$churches" :value="$selected ?? $churches->keys()->first()" required />
            <x-ff.input name="title" :label="__('Title')" maxlength="200" required :value="$prayer?->title" :placeholder="__('e.g. God provided school fees')" />
            <x-ff.textarea name="body" :label="__('Your story')" rows="8" maxlength="5000" required :value="$prayer?->answer_note" />
            <x-ff.checkbox name="is_anonymous" :label="__('Publish without my name')" switch />
            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-ff-primary"><i class="bi bi-send"></i> {{ __('Submit for review') }}</button>
                <a href="{{ route('testimonies.index') }}" class="btn btn-ff-ghost">{{ __('Cancel') }}</a>
            </div>
        </form>
    @endif
</x-layouts.member>
