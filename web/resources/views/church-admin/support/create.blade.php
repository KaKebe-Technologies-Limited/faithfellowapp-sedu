<x-layouts.church-admin :church="$church" :title="__('New support ticket')">
    <x-ff.page-header :title="__('New support ticket')" :breadcrumbs="[__('Support') => route('church-admin.support.index', $church), __('New') => null]" />

    <form method="POST" action="{{ route('church-admin.support.store', $church) }}" class="ff-card" style="max-width: 760px" novalidate>
        @csrf
        <x-ff.input name="subject" :label="__('Subject')" required maxlength="200" :placeholder="__('e.g. MTN MoMo gifts stuck on pending')" />
        <div class="row">
            <div class="col-md-6"><x-ff.select name="category" :label="__('Category')" :options="collect(\App\Models\SupportTicket::CATEGORIES)->map(fn ($l) => __($l))" required /></div>
            <div class="col-md-6"><x-ff.select name="priority" :label="__('Priority')" :options="collect(\App\Models\SupportTicket::PRIORITIES)->map(fn ($l) => __($l))" value="normal" required /></div>
        </div>
        <x-ff.textarea name="body" :label="__('Describe the problem')" rows="7" required maxlength="5000"
            :help="__('Include what you were doing, what you expected, and any receipt or reference numbers. Never share passwords or PINs.')" />
        <div class="d-flex gap-2">
            <button class="btn btn-ff-primary"><i class="bi bi-send"></i> {{ __('Open ticket') }}</button>
            <a href="{{ route('church-admin.support.index', $church) }}" class="btn btn-ff-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.church-admin>
