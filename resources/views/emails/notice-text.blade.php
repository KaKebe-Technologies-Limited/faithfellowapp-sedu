{{ $title }}

@foreach ($lines as $line)
{{ $line }}

@endforeach
@foreach ($details ?? [] as $label => $value)
{{ $label }}: {{ $value }}
@endforeach
@if (! empty($button))

{{ $button['label'] }}: {{ $button['url'] }}
@endif
@if (! empty($note))

{{ $note }}
@endif

FaithFellows
