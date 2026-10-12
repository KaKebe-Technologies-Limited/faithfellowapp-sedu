<x-layouts.super-admin :title="__('Platform settings')">
    <x-ff.page-header :title="__('Platform settings')" :subtitle="__('Platform-wide switches and messages. API keys and passwords live in the server’s .env file.')">
        <a href="{{ route('super-admin.settings.gateways') }}" class="btn btn-ff-soft"><i class="bi bi-credit-card-2-front"></i> {{ __('Payment gateways') }}</a>
    </x-ff.page-header>

    <div class="row g-4">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('super-admin.settings.update') }}" class="ff-card">@csrf @method('PUT')
                @foreach (\App\Http\Controllers\SuperAdmin\SettingsController::SETTINGS as $key => [$label, $type, $help])
                    @if ($type === 'bool')
                        <x-ff.checkbox :name="$key" :label="__($label)" :checked="(string) $values[$key] !== '0'" switch :help="$help ? __($help) : null" />
                    @else
                        <x-ff.input :name="$key" :type="$type" :label="__($label)" :value="$values[$key]" :help="$help ? __($help) : null" />
                    @endif
                @endforeach
                <button class="btn btn-ff-primary">{{ __('Save settings') }}</button>
            </form>
        </div>
        <div class="col-lg-5">
            <x-ff.card :title="__('Integrations')" :subtitle="__('Read from the server environment')">
                @foreach ($integrations as $i)
                    <div class="d-flex align-items-center gap-2 py-2 @if (! $loop->first) border-top @endif">
                        <i @class(['bi fs-5', 'bi-check-circle-fill text-success' => $i['ok'], 'bi-dash-circle text-ff-muted' => ! $i['ok']])></i>
                        <div class="flex-grow-1"><div class="small fw-semibold text-ff-black">{{ $i['name'] }}</div><div class="small-xs text-ff-muted">{{ $i['detail'] }}</div></div>
                    </div>
                @endforeach
            </x-ff.card>
        </div>
    </div>
</x-layouts.super-admin>
