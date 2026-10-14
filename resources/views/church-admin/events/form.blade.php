<x-layouts.church-admin :church="$church" :title="$event->exists ? __('Edit event') : __('New event')">
    <x-ff.page-header :title="$event->exists ? __('Edit event') : __('New event')"
        :breadcrumbs="[__('Events') => route('church-admin.events.index', $church), ($event->exists ? $event->title : __('New')) => null]" />

    @php
        $agenda = old('agenda', $event->agenda ?: [['time' => '', 'title' => '', 'speaker' => '']]);
        $speakers = old('speakers', $event->speakers ?: []);
        $tickets = old('tickets', $event->exists ? $event->tickets->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'price' => (float) $t->price, 'quantity' => $t->quantity])->all() : []);
    @endphp
    <form method="POST" enctype="multipart/form-data" action="{{ $event->exists ? route('church-admin.events.update', [$church, $event]) : route('church-admin.events.store', $church) }}"
          class="row g-4" x-data="{ online: {{ old('is_online', $event->is_online) ? 'true' : 'false' }}, paid: {{ old('is_paid', $event->is_paid) ? 'true' : 'false' }}, agenda: @js(array_values($agenda)), speakers: @js(array_values($speakers)), tickets: @js(array_values($tickets)) }" novalidate>
        @csrf
        @if ($event->exists) @method('PUT') @endif
        <div class="col-lg-8 d-grid gap-4 align-content-start">
            <div class="ff-card">
                <x-ff.input name="title" :label="__('Title')" :value="$event->title" required />
                <div class="row">
                    <div class="col-md-6"><x-ff.input name="starts_at" type="datetime-local" :label="__('Starts')" :value="$event->starts_at?->format('Y-m-d\TH:i')" required /></div>
                    <div class="col-md-6"><x-ff.input name="ends_at" type="datetime-local" :label="__('Ends')" :value="$event->ends_at?->format('Y-m-d\TH:i')" /></div>
                </div>
                <x-ff.textarea name="description" :label="__('Description')" :value="$event->description" editor />
                <label class="form-label" for="cover">{{ __('Cover image') }}</label>
                <input type="file" id="cover" name="cover" accept="image/*" class="form-control @error('cover') is-invalid @enderror">
            </div>

            <div class="ff-card">
                <h2 class="h6">{{ __('Location') }}</h2>
                <div class="form-check form-switch mb-3"><input type="hidden" name="is_online" value="0"><input class="form-check-input" type="checkbox" name="is_online" value="1" id="is_online" x-model="online"><label class="form-check-label" for="is_online">{{ __('Online event') }}</label></div>
                <div x-show="online" x-cloak><x-ff.input name="online_url" type="url" :label="__('Meeting / stream link')" :value="$event->online_url" :help="__('Only shown to people who RSVP.')" /></div>
                <div class="row">
                    <div class="col-md-6"><x-ff.input name="location_name" :label="__('Venue')" :value="$event->location_name" /></div>
                    <div class="col-md-6"><x-ff.input name="address" :label="__('Address')" :value="$event->address" /></div>
                    <div class="col-6"><x-ff.input name="lat" type="number" step="any" :label="__('Latitude')" :value="$event->lat" /></div>
                    <div class="col-6"><x-ff.input name="lng" type="number" step="any" :label="__('Longitude')" :value="$event->lng" /></div>
                </div>
            </div>

            <div class="ff-card">
                <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h6 mb-0">{{ __('Agenda') }}</h2><button type="button" class="btn btn-sm btn-ff-soft" @click="agenda.push({time:'',title:'',speaker:''})"><i class="bi bi-plus"></i></button></div>
                <template x-for="(a, i) in agenda" :key="i">
                    <div class="row g-2 mb-2">
                        <div class="col-3"><input class="form-control form-control-sm" :name="'agenda['+i+'][time]'" x-model="a.time" placeholder="10:00" aria-label="{{ __('Time') }}"></div>
                        <div class="col-5"><input class="form-control form-control-sm" :name="'agenda['+i+'][title]'" x-model="a.title" placeholder="{{ __('Worship') }}" aria-label="{{ __('Item') }}"></div>
                        <div class="col-3"><input class="form-control form-control-sm" :name="'agenda['+i+'][speaker]'" x-model="a.speaker" placeholder="{{ __('Speaker') }}" aria-label="{{ __('Speaker') }}"></div>
                        <div class="col-1"><button type="button" class="btn btn-sm btn-ff-ghost btn-icon" @click="agenda.splice(i,1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x"></i></button></div>
                    </div>
                </template>
                <div class="d-flex justify-content-between align-items-center mt-3 mb-2"><h2 class="h6 mb-0">{{ __('Speakers') }}</h2><button type="button" class="btn btn-sm btn-ff-soft" @click="speakers.push({name:'',role:'',bio:''})"><i class="bi bi-plus"></i></button></div>
                <template x-for="(s, i) in speakers" :key="i">
                    <div class="row g-2 mb-2">
                        <div class="col-4"><input class="form-control form-control-sm" :name="'speakers['+i+'][name]'" x-model="s.name" placeholder="{{ __('Name') }}" aria-label="{{ __('Name') }}"></div>
                        <div class="col-3"><input class="form-control form-control-sm" :name="'speakers['+i+'][role]'" x-model="s.role" placeholder="{{ __('Role') }}" aria-label="{{ __('Role') }}"></div>
                        <div class="col-4"><input class="form-control form-control-sm" :name="'speakers['+i+'][bio]'" x-model="s.bio" placeholder="{{ __('Short bio') }}" aria-label="{{ __('Bio') }}"></div>
                        <div class="col-1"><button type="button" class="btn btn-sm btn-ff-ghost btn-icon" @click="speakers.splice(i,1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x"></i></button></div>
                    </div>
                </template>
            </div>
        </div>

        <div class="col-lg-4 d-grid gap-4 align-content-start">
            <div class="ff-card">
                <x-ff.select name="status" :label="__('Status')" :options="['published' => __('Published'), 'draft' => __('Draft'), 'cancelled' => __('Cancelled')]" :value="$event->status" />
                <x-ff.select name="visibility" :label="__('Who can see it')" :options="['public' => __('Everyone'), 'members' => __('Members only')]" :value="$event->visibility" />
                @if ($branches->count() > 1)
                    <x-ff.select name="branch_id" :label="__('Branch')" :options="$branches" :value="$event->branch_id" :placeholder="__('All branches')" />
                    <x-ff.checkbox name="is_cross_branch" :label="__('Cross-branch event')" :checked="$event->is_cross_branch" switch />
                @endif
                <button class="btn btn-ff-primary w-100">{{ __('Save event') }}</button>
            </div>
            <div class="ff-card">
                <h2 class="h6">{{ __('Registration') }}</h2>
                <x-ff.checkbox name="rsvp_enabled" :label="__('Enable RSVP')" :checked="$event->rsvp_enabled" switch />
                <x-ff.input name="capacity" type="number" :label="__('Capacity')" :value="$event->capacity" min="1" :help="__('Leave empty for unlimited.')" />
                <x-ff.checkbox name="qa_enabled" :label="__('Live Q&A')" :checked="$event->qa_enabled" switch />
                <div class="form-check form-switch mb-2"><input type="hidden" name="is_paid" value="0"><input class="form-check-input" type="checkbox" name="is_paid" value="1" id="is_paid" x-model="paid"><label class="form-check-label" for="is_paid">{{ __('Paid tickets') }}</label></div>
                <div x-show="paid" x-cloak>
                    <template x-for="(t, i) in tickets" :key="i">
                        <div class="ff-card ff-card-gray ff-card-sm mb-2">
                            <input type="hidden" :name="'tickets['+i+'][id]'" :value="t.id ?? ''">
                            <input class="form-control form-control-sm mb-1" :name="'tickets['+i+'][name]'" x-model="t.name" placeholder="{{ __('Ticket name, e.g. Adult') }}" aria-label="{{ __('Ticket name') }}">
                            <div class="d-flex gap-1">
                                <input type="number" class="form-control form-control-sm" :name="'tickets['+i+'][price]'" x-model="t.price" placeholder="UGX" aria-label="{{ __('Price') }}">
                                <input type="number" class="form-control form-control-sm" :name="'tickets['+i+'][quantity]'" x-model="t.quantity" placeholder="{{ __('Qty') }}" aria-label="{{ __('Quantity') }}">
                                <button type="button" class="btn btn-sm btn-ff-ghost btn-icon" @click="tickets.splice(i,1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x"></i></button>
                            </div>
                        </div>
                    </template>
                    <button type="button" class="btn btn-sm btn-ff-soft w-100" @click="tickets.push({name:'', price:'', quantity:''})"><i class="bi bi-plus"></i> {{ __('Add ticket type') }}</button>
                </div>
            </div>
            <div class="ff-card">
                <h2 class="h6">{{ __('After the event') }}</h2>
                <x-ff.input name="recording_url" type="url" :label="__('Recording link')" :value="$event->recording_url" :help="__('Publish it as a sermon from the event page.')" />
            </div>
        </div>
    </form>
</x-layouts.church-admin>
