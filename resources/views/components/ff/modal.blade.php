@props(['id', 'title', 'size' => null])
<div class="modal fade ff-modal" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}-title" aria-hidden="true">
    <div @class(['modal-dialog modal-dialog-centered modal-dialog-scrollable', 'modal-'.$size => $size])>
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="{{ $id }}-title">{{ $title }}</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
            </div>
            <div class="modal-body">{{ $slot }}</div>
            @isset($footer)<div class="modal-footer">{{ $footer }}</div>@endisset
        </div>
    </div>
</div>
