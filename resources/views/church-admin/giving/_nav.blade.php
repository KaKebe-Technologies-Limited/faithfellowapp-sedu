@php($canManage = auth()->user()->canInChurch($church, 'giving.manage'))
<nav class="ff-tabs mb-4" aria-label="{{ __('Finance') }}">
    <a href="{{ route('church-admin.giving.index', $church) }}" @class(['ff-tab', 'active' => request()->routeIs('church-admin.giving.index')])>{{ __('Overview') }}</a>
    <a href="{{ route('church-admin.giving.transactions', $church) }}" @class(['ff-tab', 'active' => request()->routeIs('church-admin.giving.transactions')])>{{ __('Transactions') }}</a>
    <a href="{{ route('church-admin.payouts.index', $church) }}" @class(['ff-tab', 'active' => request()->routeIs('church-admin.payouts.*')])>{{ __('Payouts') }}</a>
    @if ($canManage)
        <a href="{{ route('church-admin.reconciliation.index', $church) }}" @class(['ff-tab', 'active' => request()->routeIs('church-admin.reconciliation.*')])>{{ __('Reconciliation') }}</a>
    @endif
    @if (auth()->user()->canInChurch($church, 'settings.manage'))
        <a href="{{ route('church-admin.settings.giving', $church) }}" class="ff-tab">{{ __('Giving channels') }}</a>
    @endif
</nav>
