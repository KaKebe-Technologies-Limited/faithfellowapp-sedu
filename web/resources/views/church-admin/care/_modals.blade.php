{{-- Shared care modals. $memberId fixes the member (care record page); null shows a picker. --}}
<x-ff.modal id="visit-modal" :title="__('Schedule a home visit')">
    <form method="POST" action="{{ route('church-admin.care.visits.store', $church) }}" id="visit-form">@csrf
        @if ($memberId)
            <input type="hidden" name="user_id" value="{{ $memberId }}">
        @else
            <x-ff.select name="user_id" :label="__('Member')" :options="$people" :placeholder="__('— Choose —')" required />
        @endif
        <x-ff.input type="datetime-local" name="scheduled_at" :label="__('When')" required />
        <x-ff.input name="purpose" :label="__('Purpose')" maxlength="200" :placeholder="__('e.g. Pray with the family after the funeral')" />
        @isset($staff)
            <x-ff.select name="visitor_id" :label="__('Who will visit')" :options="$staff" :value="auth()->id()" />
        @endisset
    </form>
    <x-slot:footer><button form="visit-form" class="btn btn-ff-primary">{{ __('Schedule') }}</button></x-slot:footer>
</x-ff.modal>

<x-ff.modal id="welfare-modal" :title="__('Record welfare support')">
    <form method="POST" action="{{ route('church-admin.care.welfare.store', $church) }}" id="welfare-form">@csrf
        @if ($memberId)
            <input type="hidden" name="user_id" value="{{ $memberId }}">
        @else
            <x-ff.select name="user_id" :label="__('Member')" :options="$people" :placeholder="__('— Not a member —')" />
            <x-ff.input name="recipient_name" :label="__('Or recipient name')" maxlength="120" />
        @endif
        <div class="row">
            <div class="col-6"><x-ff.select name="type" :label="__('Type')" :options="collect(\App\Models\WelfareRecord::TYPES)->map(fn ($l) => __($l))" required /></div>
            <div class="col-6"><x-ff.input type="number" name="amount" :label="__('Value (UGX)')" min="0" step="500" /></div>
        </div>
        <x-ff.input type="date" name="given_on" :label="__('Date')" :value="today()->toDateString()" max="{{ today()->toDateString() }}" required />
        <x-ff.textarea name="notes" :label="__('Notes (encrypted)')" rows="2" maxlength="2000" />
    </form>
    <x-slot:footer><button form="welfare-form" class="btn btn-ff-primary">{{ __('Save') }}</button></x-slot:footer>
</x-ff.modal>
