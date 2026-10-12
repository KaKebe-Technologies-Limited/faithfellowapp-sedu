<x-layouts.super-admin :title="__('Plans')">
    <x-ff.page-header :title="__('Plans')" :subtitle="__('Pricing and usage limits for churches.')" />

    <div class="row g-4">
        @foreach ($plans->push(new \App\Models\Plan(['interval' => 'month', 'storage_mb' => 5120, 'stream_hours' => 40, 'max_admins' => 10, 'max_branches' => 5, 'is_active' => true, 'currency' => 'UGX'])) as $plan)
            <div class="col-md-6 col-xl-4">
                <form method="POST" action="{{ $plan->exists ? route('super-admin.plans.update', $plan) : route('super-admin.plans.store') }}" class="ff-card h-100">@csrf
                    @if ($plan->exists) @method('PUT') @endif
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 mb-0">{{ $plan->exists ? $plan->name : __('New plan') }}</h2>
                        @if ($plan->exists)<span class="small text-ff-muted">{{ trans_choice(':count church|:count churches', $plan->churches_count) }}</span>@endif
                    </div>
                    @php($p = $plan->id ?? 'new')
                    <x-ff.input name="name" :label="__('Name')" :value="$plan->name" :id="'name'.$p" required />
                    <div class="row">
                        <div class="col-7"><x-ff.input name="price" type="number" :label="__('Price (UGX)')" :value="$plan->price ? (float) $plan->price : null" :id="'price'.$p" required /></div>
                        <div class="col-5"><x-ff.select name="interval" :label="__('Per')" :options="['month' => __('month'), 'year' => __('year')]" :value="$plan->interval" :id="'int'.$p" /></div>
                        <div class="col-6"><x-ff.input name="storage_mb" type="number" :label="__('Storage MB')" :value="$plan->storage_mb" :id="'st'.$p" /></div>
                        <div class="col-6"><x-ff.input name="stream_hours" type="number" :label="__('Stream h/mo')" :value="$plan->stream_hours" :id="'sh'.$p" /></div>
                        <div class="col-6"><x-ff.input name="max_admins" type="number" :label="__('Staff accounts')" :value="$plan->max_admins" :id="'ma'.$p" /></div>
                        <div class="col-6"><x-ff.input name="max_branches" type="number" :label="__('Branches')" :value="$plan->max_branches" :id="'mb'.$p" /></div>
                    </div>
                    <x-ff.textarea name="features" :label="__('Features (one per line)')" :value="implode(PHP_EOL, $plan->features ?? [])" rows="4" :id="'f'.$p" />
                    <x-ff.checkbox name="is_active" :label="__('Active')" :checked="$plan->is_active" :id="'a'.$p" switch />
                    <x-ff.checkbox name="is_default" :label="__('Default for new churches')" :checked="$plan->is_default" :id="'d'.$p" switch />
                    <button class="btn btn-ff-primary w-100 mt-2">{{ $plan->exists ? __('Save') : __('Create plan') }}</button>
                </form>
            </div>
        @endforeach
    </div>
</x-layouts.super-admin>
