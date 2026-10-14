@if ($sections['campaigns']->isEmpty())
    <x-ff.empty-state icon="bi-flag" :title="__('No active campaigns')" />
@else
    <div class="row g-4">
        @foreach ($sections['campaigns'] as $campaign)
            <div class="col-md-6">@include('member.campaigns._card', ['campaign' => $campaign])</div>
        @endforeach
    </div>
@endif
