<x-layouts.super-admin :title="__('Onboard a church')">
    <x-ff.page-header :title="__('Onboard a church')" :subtitle="__('Create a church space and invite its first admin by email.')"
        :breadcrumbs="[__('Churches') => route('super-admin.churches.index'), __('New') => null]" />

    <form method="POST" action="{{ route('super-admin.churches.store') }}" class="row g-4" novalidate>
        @csrf
        <div class="col-lg-8">
            <div class="ff-card">@include('partials.church-fields')</div>
        </div>
        <div class="col-lg-4">
            <div class="ff-card">
                <h2 class="h6">{{ __('Church admin') }}</h2>
                <p class="small text-ff-muted">{{ __('If the email has no account yet, one is created and they receive a link to set their password.') }}</p>
                <x-ff.input name="admin_name" :label="__('Full name')" required />
                <x-ff.input name="admin_email" type="email" :label="__('Email')" required />
                <x-ff.checkbox name="activate" :label="__('Approve immediately')" checked switch />
                <button class="btn btn-ff-primary w-100 mt-2">{{ __('Create & send invitation') }}</button>
            </div>
        </div>
    </form>
</x-layouts.super-admin>
