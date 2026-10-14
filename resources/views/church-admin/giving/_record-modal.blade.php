@if (auth()->user()->canInChurch($church, 'giving.manage'))
    <x-ff.modal id="recordGift" :title="__('Record a cash or bank gift')">
        <form method="POST" action="{{ route('church-admin.giving.record', $church) }}" id="recordGiftForm" novalidate>@csrf
            <div class="row">
                <div class="col-6"><x-ff.input name="amount" type="number" :label="__('Amount (UGX)')" min="100" required /></div>
                <div class="col-6"><x-ff.select name="method" :label="__('Method')" :options="['cash' => __('Cash'), 'bank' => __('Bank / cheque')]" value="cash" /></div>
            </div>
            <x-ff.select name="category" :label="__('Category')" :options="collect(config('faithfellows.giving_categories'))->except(['event', 'pledge'])->map(fn ($l) => __($l))" value="offering" />
            <x-ff.input name="giver_name" :label="__('Giver name (optional)')" :help="__('Leave empty for loose offering.')" />
            <x-ff.textarea name="note" :label="__('Note')" rows="2" />
            <x-ff.checkbox name="is_anonymous" :label="__('Anonymous')" />
        </form>
        <x-slot:footer><button form="recordGiftForm" class="btn btn-ff-primary px-4">{{ __('Record & issue receipt') }}</button></x-slot:footer>
    </x-ff.modal>
@endif
