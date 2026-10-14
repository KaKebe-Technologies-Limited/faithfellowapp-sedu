<x-layouts.church-admin :church="$church" :title="$campaign->exists ? __('Edit campaign') : __('New campaign')">
    <x-ff.page-header :title="$campaign->exists ? __('Edit campaign') : __('Launch a campaign')"
        :breadcrumbs="[__('Campaigns') => route('church-admin.campaigns.index', $church), ($campaign->exists ? $campaign->title : __('New')) => null]" />

    @php($teams = old('teams', $campaign->exists ? $campaign->teams->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'goal_amount' => $t->goal_amount ? (float) $t->goal_amount : '', 'color' => $t->color])->all() : []))
    <form method="POST" enctype="multipart/form-data" action="{{ $campaign->exists ? route('church-admin.campaigns.update', [$church, $campaign]) : route('church-admin.campaigns.store', $church) }}"
          class="row g-4" x-data="{ teams: @js(array_values($teams)) }" novalidate>
        @csrf
        @if ($campaign->exists) @method('PUT') @endif
        <div class="col-lg-8 d-grid gap-4 align-content-start">
            <div class="ff-card">
                <x-ff.input name="title" :label="__('Title')" :value="$campaign->title" required :placeholder="__('e.g. New Sanctuary Building Fund')" />
                <div class="row">
                    <div class="col-md-6"><x-ff.select name="type" :label="__('Type')" :options="config('faithfellows.campaign_types')" :value="$campaign->type" /></div>
                    <div class="col-md-6"><x-ff.input name="goal_amount" type="number" :label="__('Goal (UGX)')" :value="$campaign->goal_amount ? (float) $campaign->goal_amount : null" min="1000" required /></div>
                </div>
                <x-ff.input name="short_description" :label="__('One-line summary')" :value="$campaign->short_description" maxlength="300" />
                <x-ff.textarea name="story" :label="__('Full story')" :value="$campaign->story" editor :help="__('Why this matters, what the money buys, and how people can help.')" />
            </div>
            <div class="ff-card">
                <h2 class="h6">{{ __('Photos') }}</h2>
                <div class="row">
                    <div class="col-md-6"><label class="form-label" for="cover">{{ __('Cover image') }}</label><input type="file" id="cover" name="cover" accept="image/*" class="form-control @error('cover') is-invalid @enderror"></div>
                    <div class="col-md-6"><label class="form-label" for="gallery">{{ __('Gallery (up to 10)') }}</label><input type="file" id="gallery" name="gallery[]" accept="image/*" multiple class="form-control @error('gallery.*') is-invalid @enderror"></div>
                </div>
            </div>
            <div class="ff-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div><h2 class="h6 mb-0">{{ __('Team fundraising (optional)') }}</h2><p class="small text-ff-muted mb-0">{{ __('e.g. Youth vs Women’s Guild — givers pick a team.') }}</p></div>
                    <button type="button" class="btn btn-sm btn-ff-soft" @click="teams.push({ name: '', goal_amount: '', color: '#BC2649' })"><i class="bi bi-plus"></i> {{ __('Team') }}</button>
                </div>
                <template x-for="(team, i) in teams" :key="i">
                    <div class="row g-2 mb-2 align-items-center">
                        <input type="hidden" :name="'teams['+i+'][id]'" :value="team.id ?? ''">
                        <div class="col-5"><input class="form-control form-control-sm" :name="'teams['+i+'][name]'" x-model="team.name" placeholder="{{ __('Team name') }}" aria-label="{{ __('Team name') }}"></div>
                        <div class="col-4"><input type="number" class="form-control form-control-sm" :name="'teams['+i+'][goal_amount]'" x-model="team.goal_amount" placeholder="{{ __('Goal (optional)') }}" aria-label="{{ __('Goal') }}"></div>
                        <div class="col-2"><input type="color" class="form-control form-control-sm form-control-color w-100" :name="'teams['+i+'][color]'" x-model="team.color" aria-label="{{ __('Colour') }}"></div>
                        <div class="col-1"><button type="button" class="btn btn-sm btn-ff-ghost btn-icon" @click="teams.splice(i, 1)" aria-label="{{ __('Remove') }}"><i class="bi bi-x"></i></button></div>
                    </div>
                </template>
            </div>
        </div>
        <div class="col-lg-4 d-grid gap-4 align-content-start">
            <div class="ff-card">
                <x-ff.select name="status" :label="__('Status')" :options="['active' => __('Active'), 'draft' => __('Draft'), 'completed' => __('Completed'), 'cancelled' => __('Cancelled')]" :value="$campaign->status" />
                <x-ff.select name="visibility" :label="__('Who can see it')" :options="['public' => __('Everyone (shareable)'), 'members' => __('Members only')]" :value="$campaign->visibility" />
                <div class="row">
                    <div class="col-6"><x-ff.input name="starts_at" type="date" :label="__('Starts')" :value="$campaign->starts_at?->format('Y-m-d')" /></div>
                    <div class="col-6"><x-ff.input name="ends_at" type="date" :label="__('Ends')" :value="$campaign->ends_at?->format('Y-m-d')" /></div>
                </div>
                @if ($branches->count() > 1)<x-ff.select name="branch_id" :label="__('Branch')" :options="$branches" :value="$campaign->branch_id" :placeholder="__('Whole church')" />@endif
                <x-ff.checkbox name="show_leaderboard" :label="__('Show top givers (opt-in only)')" :checked="$campaign->show_leaderboard" switch />
                <x-ff.checkbox name="allow_anonymous" :label="__('Allow anonymous gifts')" :checked="$campaign->allow_anonymous" switch />
                <x-ff.checkbox name="is_featured" :label="__('Feature on member dashboards')" :checked="$campaign->is_featured" switch />
                <button class="btn btn-ff-primary w-100 mt-2">{{ $campaign->exists ? __('Save changes') : __('Launch campaign') }}</button>
                <p class="small text-ff-muted mt-2 mb-0">{{ __('Milestones at 25%, 50%, 75% and 100% are created automatically and celebrated with your members.') }}</p>
            </div>
        </div>
    </form>
</x-layouts.church-admin>
