<x-layouts.church-admin :church="$church" :title="$announcement->exists ? __('Edit announcement') : __('New announcement')">
    <x-ff.page-header :title="$announcement->exists ? __('Edit announcement') : __('New announcement')"
        :breadcrumbs="[__('Announcements') => route('church-admin.announcements.index', $church), ($announcement->exists ? $announcement->title : __('New')) => null]" />

    @php($channels = old('channels', $announcement->channels ?? []))
    <form method="POST" enctype="multipart/form-data" action="{{ $announcement->exists ? route('church-admin.announcements.update', [$church, $announcement]) : route('church-admin.announcements.store', $church) }}"
          class="row g-4" x-data="{ audience: '{{ old('audience', $announcement->audience) }}' }" novalidate>
        @csrf
        @if ($announcement->exists) @method('PUT') @endif
        <div class="col-lg-8">
            <div class="ff-card">
                <x-ff.input name="title" :label="__('Title')" :value="$announcement->title" required />
                <x-ff.textarea name="body" :label="__('Message')" :value="$announcement->body" editor required />
                <label class="form-label" for="image">{{ __('Image (optional)') }}</label>
                <input type="file" id="image" name="image" accept="image/*" class="form-control @error('image') is-invalid @enderror">
                @error('image')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="col-lg-4 d-grid gap-4 align-content-start">
            <div class="ff-card">
                <h2 class="h6">{{ __('Audience') }}</h2>
                <select name="audience" class="form-select mb-3" x-model="audience" aria-label="{{ __('Audience') }}">
                    <option value="members">{{ __('All members') }}</option>
                    <option value="public">{{ __('Everyone (public)') }}</option>
                    <option value="staff">{{ __('Staff only') }}</option>
                    @if ($branches->count() > 1)<option value="branch">{{ __('A branch') }}</option>@endif
                    @if ($groups->isNotEmpty())<option value="group">{{ __('A small group') }}</option>@endif
                </select>
                <div x-show="audience === 'branch'" x-cloak><x-ff.select name="branch_id" :label="__('Branch')" :options="$branches" :value="$announcement->branch_id" :placeholder="__('Choose…')" /></div>
                <div x-show="audience === 'group'" x-cloak><x-ff.select name="small_group_id" :label="__('Group')" :options="$groups" :value="$announcement->small_group_id" :placeholder="__('Choose…')" /></div>
                <x-ff.select name="priority" :label="__('Priority')" :options="['normal' => __('Normal'), 'urgent' => __('Urgent')]" :value="$announcement->priority" />
                <label class="form-label">{{ __('Also send by') }}</label>
                @foreach (['mail' => __('Email'), 'sms' => __('SMS'), 'whatsapp' => __('WhatsApp')] as $key => $label)
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="channels[]" value="{{ $key }}" id="ch_{{ $key }}" @checked(in_array($key, $channels, true))><label class="form-check-label" for="ch_{{ $key }}">{{ $label }}</label></div>
                @endforeach
                <div class="form-text mb-2">{{ __('In-app delivery is always on. SMS/WhatsApp use your church’s message credits.') }}</div>
            </div>
            <div class="ff-card">
                <x-ff.select name="status" :label="__('Status')" :options="['published' => __('Publish'), 'draft' => __('Draft')]" :value="$announcement->status === 'draft' ? 'draft' : 'published'" />
                <x-ff.input name="published_at" type="datetime-local" :label="__('Send at')" :value="$announcement->published_at?->format('Y-m-d\TH:i')" :help="__('Empty = now. Future time = scheduled.')" />
                <x-ff.input name="expires_at" type="datetime-local" :label="__('Hide after')" :value="$announcement->expires_at?->format('Y-m-d\TH:i')" />
                <button class="btn btn-ff-primary w-100"><i class="bi bi-send"></i> {{ __('Save announcement') }}</button>
            </div>
        </div>
    </form>
</x-layouts.church-admin>
