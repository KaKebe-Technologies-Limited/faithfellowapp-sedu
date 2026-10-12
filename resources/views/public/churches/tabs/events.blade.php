@if ($sections['events']->isEmpty())
    <x-ff.empty-state icon="bi-calendar-event" :title="__('No upcoming events')" />
@else
    <div class="d-grid gap-3">
        @foreach ($sections['events'] as $event)
            @include('member.events._row', ['event' => $event])
        @endforeach
    </div>
@endif
