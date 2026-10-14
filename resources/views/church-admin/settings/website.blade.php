<x-layouts.church-admin :church="$church" :title="__('Website builder')">
    <x-ff.page-header :title="__('Settings')" />
    @include('church-admin.settings._nav')

    <div class="row g-4">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('church-admin.settings.website.update', $church) }}" class="ff-card"
                  x-data="blockEditor({ blocks: @js($blocks), labels: @js($blockLabels) })" novalidate>
                @csrf
                @method('PUT')
                <div class="ff-card-header">
                    <div>
                        <h2>{{ __('Page sections') }}</h2>
                        <p class="small text-ff-muted mb-0">{{ __('Drag to reorder. Switch sections on or off. No coding needed.') }}</p>
                    </div>
                    <a href="{{ ff_church_url($church) }}" target="_blank" class="btn btn-sm btn-ff-soft"><i class="bi bi-eye"></i> {{ __('Preview') }}</a>
                </div>

                <ul class="list-unstyled d-grid gap-2 mb-4" role="list">
                    <template x-for="(block, i) in blocks" :key="block.type">
                        <li class="ff-card ff-card-sm ff-card-flat d-flex align-items-center gap-3 ff-block-editor-item"
                            draggable="true"
                            :class="{ 'is-dragging': dragIndex === i, 'is-over': overIndex === i && dragIndex !== i }"
                            @dragstart="start(i)" @dragover.prevent="over(i)" @drop.prevent="drop(i)" @dragend="reset()">
                            <i class="bi bi-grip-vertical text-ff-muted" aria-hidden="true"></i>
                            <span class="ff-feature-icon mb-0" style="width:36px;height:36px;font-size:1rem"><i class="bi" :class="labels[block.type][1]"></i></span>
                            <span class="fw-semibold text-ff-black flex-grow-1" x-text="labels[block.type][0]"></span>
                            <button type="button" class="btn btn-sm btn-ff-ghost btn-icon" @click="move(i, -1)" :disabled="i === 0" aria-label="{{ __('Move up') }}"><i class="bi bi-arrow-up"></i></button>
                            <button type="button" class="btn btn-sm btn-ff-ghost btn-icon" @click="move(i, 1)" :disabled="i === blocks.length - 1" aria-label="{{ __('Move down') }}"><i class="bi bi-arrow-down"></i></button>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" role="switch" x-model="block.enabled" :aria-label="labels[block.type][0]">
                            </div>
                        </li>
                    </template>
                </ul>
                <input type="hidden" name="blocks" :value="json">

                <h3 class="h6">{{ __('Hero text') }}</h3>
                <x-ff.input name="hero_title" :label="__('Headline')" :value="$church->setting('hero_title')" :placeholder="$church->name" />
                <x-ff.input name="hero_subtitle" :label="__('Sub-headline')" :value="$church->setting('hero_subtitle')" :placeholder="$church->tagline ?: __('Welcome home')" />
                <button class="btn btn-ff-primary px-4">{{ __('Publish website') }}</button>
            </form>
        </div>
        <div class="col-lg-5">
            <div class="ff-card ff-card-mist">
                <h2 class="h6">{{ __('Your web addresses') }}</h2>
                <p class="small mb-2"><i class="bi bi-globe"></i> <a href="{{ ff_church_url($church) }}" target="_blank">{{ Str::after(ff_church_url($church), '://') }}</a></p>
                @if ($church->custom_domain)
                    <p class="small mb-2"><i class="bi bi-link"></i> {{ $church->custom_domain }} — {{ $church->custom_domain_verified ? __('connected') : __('awaiting DNS') }}</p>
                @else
                    <p class="small text-ff-muted mb-0">{{ __('Want www.yourchurch.org? Custom domains are UGX :price/year — contact support to connect one.', ['price' => number_format(config('faithfellows.pricing.domain_price'))]) }}</p>
                @endif
            </div>
        </div>
    </div>
</x-layouts.church-admin>
