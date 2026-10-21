<x-layouts.super-admin :title="__('Inspiration')">
    <x-ff.page-header :title="__('Inspiration')" :subtitle="__('Short, powerful messages the app shows on the home screen in the morning, mid-morning and evening. Each part of the day shows one a day.')" />

    @if (session('status'))<div class="alert alert-success py-2 small">{{ session('status') }}</div>@endif

    <div class="row g-3 mb-4">
        @foreach (\App\Models\Inspiration::SLOTS as $key => $label)
            <div class="col-md-4"><div class="ff-card h-100">
                <div class="text-ff-muted small">{{ __($label) }}</div>
                <div class="fs-4 fw-semibold">{{ $counts[$key] ?? 0 }} <span class="fs-6 text-ff-muted">{{ __('messages on') }}</span></div>
                @if ($now && $now['slot'] === $key)<span class="ff-badge ff-badge-success mt-2"><i class="bi bi-broadcast"></i> {{ __('On the home screen now') }}</span>@endif
            </div></div>
        @endforeach
    </div>

    <div class="ff-card mb-4">
        <div class="d-flex align-items-center gap-2 mb-1">
            <h2 class="h6 mb-0">{{ __('Send to phones') }}</h2>
            <span class="ff-badge {{ $push['auto'] ? 'ff-badge-success' : '' }}">{{ $push['auto'] ? __('Scheduled sending is on') : __('Scheduled sending is off') }}</span>
        </div>
        <p class="small text-ff-muted">{{ __('The message of each part of the day is pushed to everyone who has the app on a phone (:n of :total members) at the time you set. A message can only pop up on a phone that has notifications turned on; the app asks members to do that. Members can switch it off under Notification settings, “Daily inspiration”.', ['n' => number_format($push['audience']), 'total' => number_format($push['members'])]) }}</p>
        <form method="POST" action="{{ route('super-admin.inspiration.schedule') }}" class="row g-2 align-items-end">
            @csrf @method('PUT')
            @foreach (\App\Models\Inspiration::SLOTS as $key => $label)
                <div class="col-sm-4 col-md-2">
                    <label class="form-label small fw-semibold" for="time-{{ $key }}">{{ __($label) }}</label>
                    <input id="time-{{ $key }}" type="time" name="times[{{ $key }}]" value="{{ old('times.'.$key, $push['times'][$key]) }}" class="form-control" required>
                    @if ($push['last'][$key])<div class="small-xs text-ff-muted mt-1">{{ __('Last sent :t', ['t' => \Illuminate\Support\Carbon::parse($push['last'][$key])->diffForHumans()]) }}</div>@endif
                </div>
            @endforeach
            <div class="col-sm-12 col-md-3">
                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="auto" name="auto" value="1" @checked($push['auto'])><label class="form-check-label" for="auto">{{ __('Send automatically') }}</label></div>
                <button class="btn btn-ff-primary w-100"><i class="bi bi-check2"></i> {{ __('Save schedule') }}</button>
            </div>
        </form>
        @if ($now)
            <hr>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="small flex-grow-1"><strong>{{ __('Now') }}:</strong> “{{ $now['body'] }}”@if ($now['reference']) <span class="text-ff-muted">({{ $now['reference'] }})</span>@endif</div>
                @php($current = \App\Models\Inspiration::where('slot', $now['slot'])->where('body', $now['body'])->first())
                @if ($current)
                    <form method="POST" action="{{ route('super-admin.inspiration.send', $current) }}" onsubmit="return confirm('{{ __('Send this message to everyone now?') }}')">@csrf
                        <button class="btn btn-ff-ghost"><i class="bi bi-send"></i> {{ __('Send this now') }}</button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    <div class="ff-card mb-4">
        <div class="d-flex align-items-center gap-2 mb-1">
            <h2 class="h6 mb-0">{{ __('Verse of the day on phones') }}</h2>
            <span class="ff-badge {{ $verse['auto'] ? 'ff-badge-success' : '' }}">{{ $verse['auto'] ? __('Sent every morning') : __('Off') }}</span>
        </div>
        <p class="small text-ff-muted">{{ __('Each morning the verse of the day pops up on every phone that has the app (:n of :total members), with the reference and the words. Today’s verse is :ref. Members can switch it off under Notification settings, “Verse of the day”.', ['n' => number_format($push['audience']), 'total' => number_format($push['members']), 'ref' => $verse['today']]) }}</p>
        <form method="POST" action="{{ route('super-admin.inspiration.verse') }}" class="row g-2 align-items-end">
            @csrf @method('PUT')
            <div class="col-sm-4 col-md-2">
                <label class="form-label small fw-semibold" for="verse-time">{{ __('Time') }}</label>
                <input id="verse-time" type="time" name="time" value="{{ old('time', $verse['time']) }}" class="form-control" required>
                @if ($verse['last'])<div class="small-xs text-ff-muted mt-1">{{ __('Last sent :t', ['t' => \Illuminate\Support\Carbon::parse($verse['last'])->diffForHumans()]) }}</div>@endif
            </div>
            <div class="col-sm-8 col-md-4">
                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" id="verse-auto" name="auto" value="1" @checked($verse['auto'])><label class="form-check-label" for="verse-auto">{{ __('Send automatically') }}</label></div>
                <button class="btn btn-ff-primary w-100"><i class="bi bi-check2"></i> {{ __('Save') }}</button>
            </div>
        </form>
        <hr>
        <form method="POST" action="{{ route('super-admin.inspiration.verse-send') }}" onsubmit="return confirm('{{ __('Send today’s verse to everyone now?') }}')">@csrf
            <button class="btn btn-ff-ghost"><i class="bi bi-send"></i> {{ __('Send today’s verse now') }}</button>
        </form>
    </div>

    <form method="POST" action="{{ route('super-admin.inspiration.store') }}" class="ff-card mb-4">
        @csrf
        <h2 class="h6 mb-3">{{ __('Add a message') }}</h2>
        <div class="row g-2">
            <div class="col-md-3"><x-ff.select name="slot" :label="__('Part of the day')" :options="collect(\App\Models\Inspiration::SLOTS)->map(fn ($l) => __($l))->all()" :value="old('slot', 'morning')" required /></div>
            <div class="col-md-6"><x-ff.input name="body" :label="__('Message (keep it brief)')" :value="old('body')" required :help="__('Up to 280 characters.')" /></div>
            <div class="col-md-3"><x-ff.input name="reference" :label="__('Bible reference (optional)')" :value="old('reference')" :help="__('Only for a word-for-word quotation.')" /></div>
        </div>
        <button class="btn btn-ff-primary mt-1"><i class="bi bi-plus-lg"></i> {{ __('Add') }}</button>
    </form>

    <div class="ff-table-wrap">
        <div class="ff-table-toolbar">
            <div class="ff-pills">
                <a href="{{ route('super-admin.inspiration.index') }}" @class(['ff-pill', 'active' => ! $slot])>{{ __('All') }}</a>
                @foreach (\App\Models\Inspiration::SLOTS as $key => $label)
                    <a href="{{ route('super-admin.inspiration.index', ['slot' => $key]) }}" @class(['ff-pill', 'active' => $slot === $key])>{{ __($label) }}</a>
                @endforeach
            </div>
        </div>
        <div class="table-responsive">
            <table class="table ff-table align-middle">
                <thead><tr><th>{{ __('Part of the day') }}</th><th>{{ __('Message') }}</th><th>{{ __('On') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($messages as $m)
                        <tr>
                            <td colspan="4" class="p-0">
                                <form method="POST" action="{{ route('super-admin.inspiration.update', $m) }}" class="d-flex flex-wrap gap-2 align-items-center p-2">
                                    @csrf @method('PUT')
                                    <select name="slot" class="form-select form-select-sm" style="width:auto">
                                        @foreach (\App\Models\Inspiration::SLOTS as $key => $label)<option value="{{ $key }}" @selected($m->slot === $key)>{{ __($label) }}</option>@endforeach
                                    </select>
                                    <input name="body" value="{{ $m->body }}" class="form-control form-control-sm flex-grow-1" style="min-width:260px" maxlength="280" required aria-label="{{ __('Message') }}">
                                    <input name="reference" value="{{ $m->reference }}" class="form-control form-control-sm" style="width:150px" maxlength="80" placeholder="{{ __('Reference') }}" aria-label="{{ __('Bible reference') }}">
                                    <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" @checked($m->is_active) aria-label="{{ __('On') }}"></div>
                                    <button class="btn btn-sm btn-ff-soft">{{ __('Save') }}</button>
                                    <button class="btn btn-sm btn-ff-ghost" form="send-{{ $m->id }}" onclick="return confirm('{{ __('Send this message to everyone now?') }}')" title="{{ __('Send now') }}" aria-label="{{ __('Send now') }}"><i class="bi bi-send"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" form="del-{{ $m->id }}" onclick="return confirm('{{ __('Remove this message?') }}')"><i class="bi bi-trash"></i></button>
                                </form>
                                <form id="send-{{ $m->id }}" method="POST" action="{{ route('super-admin.inspiration.send', $m) }}" class="d-none">@csrf</form>
                                <form id="del-{{ $m->id }}" method="POST" action="{{ route('super-admin.inspiration.destroy', $m) }}" class="d-none">@csrf @method('DELETE')</form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.super-admin>
