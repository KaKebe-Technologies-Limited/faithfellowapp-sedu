<x-layouts.member :title="__('Give to :church', ['church' => $church->name])">
    @php
        $online = $channels->filter->isOnline();
        $bank = $channels->firstWhere('type', 'bank');
        // Diaspora mode: card first for givers outside Uganda.
        $ordered = $diaspora ? $online->sortBy(fn ($c) => $c->type === 'stripe' ? 0 : 1) : $online->sortBy(fn ($c) => $c->type === 'stripe' ? 1 : 0);
        $defaultMethod = $ordered->first()?->type ?? '';
        $methodStyles = ['mtn_momo' => ['ff-method-mtn', 'MTN'], 'airtel_money' => ['ff-method-airtel', 'airtel'], 'iotec' => ['ff-method-iotec', 'iotec'], 'stripe' => ['ff-method-card', 'VISA']];
    @endphp

    <div class="mx-auto" style="max-width: 720px">
        <div class="d-flex align-items-center gap-3 mb-4">
            <x-ff.church-avatar :church="$church" size="lg" />
            <div>
                <div class="small text-ff-muted">{{ __('Give to') }}</div>
                <h1 class="h4 mb-0">{{ $church->name }}</h1>
            </div>
        </div>

        @if ($online->isEmpty())
            <x-ff.empty-state icon="bi-wallet2" :title="__('Online giving is not set up yet')" :message="__(':church has not enabled online giving.', ['church' => $church->name])">
                @if ($bank)<div class="text-start small">{{ $bank->instructions }}</div>@endif
            </x-ff.empty-state>
        @else
            <form method="POST" action="{{ route('give.store', $church) }}" class="ff-card p-4 p-md-5"
                  x-data="givingForm({ amount: '{{ old('amount', $pledge?->installmentAmount() ?? '') }}', method: '{{ old('method', $defaultMethod) }}' })" novalidate>
                @csrf

                @if ($campaign)
                    <input type="hidden" name="campaign_id" value="{{ $campaign->id }}">
                    <div class="ff-card ff-card-mist ff-card-sm mb-4 d-flex align-items-center gap-3">
                        <x-ff.ring :value="$campaign->percent()" :size="64" :stroke="7" :label="round($campaign->percent()).'%'" sub="" />
                        <div class="min-w-0"><div class="small text-ff-muted">{{ __('Campaign') }}</div><div class="fw-bold text-ff-black text-truncate">{{ $campaign->title }}</div></div>
                    </div>
                    @if ($campaign->teams->isNotEmpty())
                        <x-ff.select name="campaign_team_id" :label="__('Give with a team (optional)')" :options="$campaign->teams->pluck('name', 'id')" :placeholder="__('No team')" />
                    @endif
                @else
                    <div class="ff-form-section">
                        <label class="form-label">{{ __('What are you giving for?') }}</label>
                        <div class="ff-pills mb-2">
                            @foreach (['tithe', 'offering', 'building_fund', 'missions', 'welfare', 'project', 'special'] as $cat)
                                <label @class(['ff-pill', 'active' => old('category', $pledge ? 'project' : 'tithe') === $cat]) style="cursor:pointer">
                                    <input type="radio" name="category" value="{{ $cat }}" class="d-none" @checked(old('category', $pledge ? 'project' : 'tithe') === $cat) onchange="this.closest('.ff-pills').querySelectorAll('.ff-pill').forEach(p => p.classList.toggle('active', p.contains(this)))">
                                    {{ __(config('faithfellows.giving_categories.'.$cat)) }}
                                </label>
                            @endforeach
                        </div>
                        @error('category')<div class="text-danger small">{{ $message }}</div>@enderror
                        @if ($campaigns->isNotEmpty())
                            <div class="small text-ff-muted">{{ __('Or give to a campaign:') }}
                                @foreach ($campaigns as $c)<a href="{{ route('give.create', [$church, 'campaign' => $c->slug]) }}" class="me-2">{{ $c->title }}</a>@endforeach
                            </div>
                        @endif
                    </div>
                @endif

                @if ($group)
                    <input type="hidden" name="small_group_id" value="{{ $group->id }}">
                    <div class="alert alert-info small"><i class="bi bi-people"></i> {{ __('Giving through :group.', ['group' => $group->name]) }}</div>
                @endif

                @if ($pledge)
                    <input type="hidden" name="pledge_id" value="{{ $pledge->id }}">
                    <div class="alert alert-info small">{{ __('Paying towards your pledge: :paid of :total paid.', ['paid' => ff_money($pledge->paid_amount), 'total' => ff_money($pledge->amount)]) }}</div>
                @endif

                <div class="ff-form-section">
                    <label class="form-label">{{ __('Amount (UGX)') }}</label>
                    <div class="ff-amount-selector mb-2">
                        @foreach (config('faithfellows.quick_amounts') as $quick)
                            <button type="button" class="ff-amount-option" :class="{ active: !custom && Number(amount) === {{ $quick }} }" @click="pick({{ $quick }})">{{ number_format($quick / 1000) }}K</button>
                        @endforeach
                        <button type="button" class="ff-amount-option" :class="{ active: custom }" @click="useCustom()">{{ __('Other') }}</button>
                    </div>
                    <div class="input-group input-group-lg" x-show="custom || amount" x-cloak>
                        <span class="input-group-text">UGX</span>
                        <input type="number" name="amount" x-ref="custom" x-model="amount" min="500" step="500" class="form-control @error('amount') is-invalid @enderror" aria-label="{{ __('Amount') }}">
                    </div>
                    @error('amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>

                <div class="ff-form-section">
                    <label class="form-label">{{ __('Pay with') }}</label>
                    @if ($diaspora)<div class="small text-ff-muted mb-2"><i class="bi bi-globe2"></i> {{ __('Giving from abroad? Card payments work in any currency.') }}</div>@endif
                    <div class="d-grid gap-2">
                        @foreach ($ordered as $channel)
                            @php([$cls, $txt] = $methodStyles[$channel->type] ?? ['ff-method-other', '•'])
                            <label class="ff-method-option">
                                <input type="radio" name="method" value="{{ $channel->type }}" class="form-check-input mt-0" x-model="method">
                                <span class="ff-method-icon {{ $cls }}">{{ $txt }}</span>
                                <span><strong class="text-ff-black d-block">{{ $channel->display_name }}</strong>
                                    <span class="small text-ff-muted">{{ $channel->type === 'stripe' ? __('Visa, Mastercard — secure checkout by Stripe') : __('A prompt is sent to your phone') }}</span></span>
                            </label>
                        @endforeach
                    </div>
                    @error('method')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    <div class="mt-3" x-show="needsPhone" x-cloak>
                        <x-ff.input name="phone" type="tel" :label="__('Mobile money number')" :value="$savedPhone" placeholder="0772 123 456" inputmode="tel" />
                    </div>
                </div>

                <div class="ff-form-section">
                    <x-ff.select name="recurring" :label="__('Make this a recurring gift?')" :options="['weekly' => __('Every week'), 'monthly' => __('Every month'), 'quarterly' => __('Every 3 months')]" :placeholder="__('No, just once')" />
                    <x-ff.checkbox name="is_anonymous" :label="__('Give anonymously (your name is hidden from leaderboards)')" />
                    @if ($campaign?->show_leaderboard)
                        <x-ff.checkbox name="show_on_leaderboard" :label="__('Show my name on the campaign leaderboard')" />
                    @endif
                    <x-ff.input name="note" :label="__('Note (optional)')" maxlength="500" />
                </div>

                <button class="btn btn-ff-primary btn-lg w-100" :disabled="!valid">
                    <i class="bi bi-heart-fill"></i> <span x-text="amount ? '{{ __('Give') }} ' + formatted : '{{ __('Choose an amount') }}'">{{ __('Give') }}</span>
                </button>
                <p class="small text-ff-muted text-center mt-3 mb-0"><i class="bi bi-shield-lock"></i> {{ __('Payments are processed securely. FaithFellows never stores card numbers or PINs. You get an instant receipt.') }}</p>
            </form>

            @if ($bank)
                <div class="ff-card ff-card-gray mt-4">
                    <h2 class="h6"><i class="bi bi-bank"></i> {{ __('Prefer a bank transfer?') }}</h2>
                    <div class="small">
                        @foreach ($bank->details ?? [] as $label => $value)<div><strong>{{ Str::headline($label) }}:</strong> {{ $value }}</div>@endforeach
                        @if ($bank->instructions)<p class="mb-0 mt-2">{{ $bank->instructions }}</p>@endif
                    </div>
                </div>
            @endif

            <div class="ff-card mt-4" id="pledge" x-data="{ open: location.hash === '#pledge' }">
                <button type="button" class="btn btn-link p-0 fw-semibold" @click="open = !open"><i class="bi bi-hand-thumbs-up"></i> {{ __('Make a pledge instead') }}</button>
                <form method="POST" action="{{ route('pledges.store', $church) }}" class="mt-3" x-show="open" x-cloak>@csrf
                    @if ($campaign)<input type="hidden" name="campaign_id" value="{{ $campaign->id }}">@endif
                    <div class="row">
                        <div class="col-md-6"><x-ff.input name="amount" type="number" :label="__('Total pledge (UGX)')" min="1000" required id="pledge_amount" /></div>
                        <div class="col-md-6"><x-ff.select name="frequency" :label="__('Pay')" :options="['once' => __('In one go'), 'weekly' => __('Weekly'), 'monthly' => __('Monthly'), 'quarterly' => __('Quarterly')]" value="monthly" /></div>
                        <div class="col-md-6"><x-ff.input name="installments" type="number" :label="__('Number of installments')" value="6" min="1" max="60" /></div>
                        <div class="col-md-6"><x-ff.input name="due_date" type="date" :label="__('Complete by (optional)')" /></div>
                    </div>
                    <button class="btn btn-ff-outline">{{ __('Make pledge') }}</button>
                </form>
            </div>
        @endif
    </div>
</x-layouts.member>
