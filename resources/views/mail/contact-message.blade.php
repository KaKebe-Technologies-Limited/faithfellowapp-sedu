<x-mail::message>
# New contact message

**From:** {{ $contactMessage->name }} ({{ $contactMessage->email }})
@if ($contactMessage->phone)
**Phone:** {{ $contactMessage->phone }}
@endif
@if ($contactMessage->subject)
**Subject:** {{ $contactMessage->subject }}
@endif

<x-mail::panel>
{{ $contactMessage->message }}
</x-mail::panel>

Reply directly to this email to respond.

{{ config('faithfellows.brand.name') }} · {{ config('faithfellows.company.name') }}
</x-mail::message>
