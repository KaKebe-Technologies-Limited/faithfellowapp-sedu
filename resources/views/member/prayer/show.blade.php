<x-layouts.member :title="$prayer->title ?: __('Prayer request')">
    <x-ff.page-header :title="$prayer->title ?: __('Prayer request')" :breadcrumbs="[__('Prayer wall') => route('prayer.index'), $prayer->church->name => null]" />

    <div class="row g-4">
        <div class="col-lg-8 d-grid gap-4 align-content-start">
            @include('member.prayer._card', ['prayed' => $prayed, 'limit' => 5000])

            <x-ff.card :title="__('Encouragement')">
                @include('partials.engagement-bar', ['model' => $prayer, 'type' => 'prayer', 'open' => true])
            </x-ff.card>
        </div>

        <aside class="col-lg-4 d-grid gap-4 align-content-start">
            <x-ff.card :title="__('Praying with you')">
                <div class="display-6 fw-bold text-ff-black">{{ number_format($prayer->prayed_count) }}</div>
                <p class="small text-ff-muted">{{ trans_choice('{0} No one has prayed yet — be the first.|{1} person has prayed.|[2,*] people have prayed.', $prayer->prayed_count) }}</p>
                @if ($prayers->isNotEmpty())
                    <div class="ff-avatar-stack">
                        @foreach ($prayers as $p)<x-ff.avatar :user="$p->user" size="sm" />@endforeach
                    </div>
                @endif
                @if ($chainLinks)
                    <p class="small mt-3 mb-0"><i class="bi bi-link-45deg text-ff-primary"></i> {{ trans_choice(':count link in the prayer chain so far.|:count links in the prayer chain so far.', $chainLinks) }}</p>
                @endif
            </x-ff.card>

            @can('update', $prayer)
                @if ($prayer->status === 'open')
                    <x-ff.card :title="__('Has God answered?')">
                        <form method="POST" action="{{ route('prayer.answered', $prayer) }}">@csrf
                            <x-ff.textarea name="answer_note" :label="__('Share what happened (optional)')" rows="3" maxlength="2000" />
                            <x-ff.checkbox name="share_testimony" :label="__('Write a full testimony next')" switch />
                            <button class="btn btn-ff-primary w-100 mt-2"><i class="bi bi-stars"></i> {{ __('Mark as answered') }}</button>
                        </form>
                    </x-ff.card>
                @endif
                <form method="POST" action="{{ route('prayer.destroy', $prayer) }}" data-ff-confirm="{{ __('Remove this request?') }}">@csrf @method('DELETE')
                    <button class="btn btn-ff-ghost text-danger w-100"><i class="bi bi-trash"></i> {{ __('Remove request') }}</button>
                </form>
            @endcan
        </aside>
    </div>
</x-layouts.member>
