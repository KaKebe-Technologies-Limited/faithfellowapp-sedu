{{-- "Report" menu for a piece of content. Expects $type (ContentReport::TYPES key) and $id. --}}
@auth
    <div class="dropdown d-inline-block">
        <button type="button" class="btn btn-link btn-sm p-0 text-ff-muted" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-label="{{ __('Report') }}" title="{{ __('Report') }}">
            <i class="bi bi-flag"></i>
        </button>
        <form method="POST" action="{{ route('reports.store', [$type, $id]) }}" class="dropdown-menu dropdown-menu-end p-3" style="min-width: 240px">@csrf
            <div class="small fw-semibold text-ff-black mb-2">{{ __('Why are you reporting this?') }}</div>
            @foreach (\App\Models\ContentReport::REASONS as $value => $label)
                <div class="form-check small">
                    <input class="form-check-input" type="radio" name="reason" value="{{ $value }}" id="r-{{ $type }}-{{ $id }}-{{ $value }}" @checked($loop->first) required>
                    <label class="form-check-label" for="r-{{ $type }}-{{ $id }}-{{ $value }}">{{ __($label) }}</label>
                </div>
            @endforeach
            <input name="details" class="form-control form-control-sm mt-2" maxlength="500" placeholder="{{ __('Details (optional)') }}" aria-label="{{ __('Details') }}">
            <button class="btn btn-sm btn-ff-primary w-100 mt-2">{{ __('Send report') }}</button>
        </form>
    </div>
@endauth
