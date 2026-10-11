{{-- Repeatable service-time rows (Alpine). Expects $services, $branches. --}}
@php
    $rows = old('services', $services->map(fn ($s) => [
        'name' => $s->name, 'day_of_week' => $s->day_of_week, 'start_time' => substr($s->start_time, 0, 5),
        'end_time' => $s->end_time ? substr($s->end_time, 0, 5) : '', 'language' => $s->language, 'branch_id' => $s->branch_id, 'location' => $s->location,
    ])->values()->all());
    if (empty($rows)) {
        $rows = [['name' => __('Sunday Service'), 'day_of_week' => 0, 'start_time' => '09:00', 'end_time' => '11:30', 'language' => 'en', 'branch_id' => null, 'location' => '']];
    }
@endphp
<div x-data="{ rows: @js($rows), add() { this.rows.push({ name: '', day_of_week: 0, start_time: '09:00', end_time: '', language: 'en', branch_id: '', location: '' }) } }">
    @error('services')<div class="alert alert-danger small">{{ $message }}</div>@enderror
    <template x-for="(row, i) in rows" :key="i">
        <div class="ff-card ff-card-gray ff-card-sm mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small" :for="'svc_name_'+i">{{ __('Name') }}</label>
                    <input class="form-control" :id="'svc_name_'+i" :name="'services['+i+'][name]'" x-model="row.name" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label small" :for="'svc_day_'+i">{{ __('Day') }}</label>
                    <select class="form-select" :id="'svc_day_'+i" :name="'services['+i+'][day_of_week]'" x-model="row.day_of_week">
                        @foreach (\App\Models\ServiceTime::DAYS as $d => $day)<option value="{{ $d }}">{{ __($day) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-3 col-md-2">
                    <label class="form-label small" :for="'svc_start_'+i">{{ __('Starts') }}</label>
                    <input type="time" class="form-control" :id="'svc_start_'+i" :name="'services['+i+'][start_time]'" x-model="row.start_time" required>
                </div>
                <div class="col-3 col-md-2">
                    <label class="form-label small" :for="'svc_end_'+i">{{ __('Ends') }}</label>
                    <input type="time" class="form-control" :id="'svc_end_'+i" :name="'services['+i+'][end_time]'" x-model="row.end_time">
                </div>
                <div class="col-10 col-md-1">
                    <label class="form-label small" :for="'svc_lang_'+i">{{ __('Lang') }}</label>
                    <select class="form-select" :id="'svc_lang_'+i" :name="'services['+i+'][language]'" x-model="row.language">
                        @foreach (config('faithfellows.languages') as $code => $label)<option value="{{ $code }}">{{ strtoupper($code) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-2 col-md-1 text-end">
                    <button type="button" class="btn btn-ff-ghost btn-icon text-danger" @click="rows.splice(i, 1)" aria-label="{{ __('Remove') }}"><i class="bi bi-trash"></i></button>
                </div>
                @if ($branches->count() > 1)
                    <div class="col-md-4">
                        <select class="form-select form-select-sm" :name="'services['+i+'][branch_id]'" x-model="row.branch_id" aria-label="{{ __('Branch') }}">
                            <option value="">{{ __('All branches') }}</option>
                            @foreach ($branches as $branch)<option value="{{ $branch->id }}">{{ $branch->name }}</option>@endforeach
                        </select>
                    </div>
                @endif
            </div>
        </div>
    </template>
    @foreach ($errors->get('services.*') as $messages)
        <div class="text-danger small">{{ $messages[0] }}</div>
    @endforeach
    <button type="button" class="btn btn-ff-soft btn-sm" @click="add()"><i class="bi bi-plus-lg"></i> {{ __('Add service') }}</button>
</div>
