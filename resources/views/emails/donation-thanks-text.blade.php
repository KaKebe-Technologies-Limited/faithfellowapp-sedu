{{ __('Thank you, :name!', ['name' => $name]) }}

{{ __('We have received your gift of :amount to :church.', ['amount' => $amount, 'church' => $church]) }}

{{ __('Given for') }}: {{ $reason }}
@if ($campaign){{ __('Fundraiser') }}: {{ $campaign->title }}
@endif
{{ __('Date') }}: {{ $date }}
{{ __('Paid with') }}: {{ $method }}
{{ __('Receipt number') }}: {{ $receipt }}

"{{ __('God loves a cheerful giver.') }}" — 2 Corinthians 9:7

{{ __('View your giving history') }}: {{ $url }}

FaithFellows
