<x-layouts.church-admin :church="$church" :title="$plan->exists ? __('Edit plan') : __('New plan')">
    <x-ff.page-header :title="$plan->exists ? __('Edit reading plan') : __('New reading plan')"
        :breadcrumbs="[__('Reading plans') => route('church-admin.reading-plans.index', $church), ($plan->exists ? $plan->title : __('New')) => null]" />

    @php($schedule = collect($plan->entries ?? [])->map(fn ($e) => rtrim(implode(' | ', [$e['passages'], $e['title'] ?? '', $e['reflection'] ?? '']), ' |'))->implode("\n"))
    <form method="POST" action="{{ $plan->exists ? route('church-admin.reading-plans.update', [$church, $plan]) : route('church-admin.reading-plans.store', $church) }}" class="ff-card" style="max-width: 900px" novalidate>
        @csrf
        @if ($plan->exists) @method('PUT') @endif
        <x-ff.input name="title" :label="__('Title')" :value="$plan->title" required />
        <x-ff.textarea name="description" :label="__('Description')" :value="$plan->description" rows="2" />
        <x-ff.textarea name="schedule" :label="__('Daily readings — one line per day')" :value="$schedule" rows="14" required
            :help="__('Format: Passages | optional title | optional reflection. Example: John 1 | The Word became flesh | What does it mean that Jesus is the Light?')" style="font-family: ui-monospace, monospace; font-size: .85rem" />
        <x-ff.checkbox name="is_published" :label="__('Published (members can start it)')" :checked="$plan->is_published" switch />
        <button class="btn btn-ff-primary px-4 mt-2">{{ __('Save plan') }}</button>
    </form>
</x-layouts.church-admin>
