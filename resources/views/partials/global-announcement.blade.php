@php($announcement = rescue(fn () => \App\Models\PlatformSetting::get('global_announcement'), null, false))
@if ($announcement)
    <div class="bg-ff-gradient text-center small py-2 px-3" role="region" aria-label="{{ __('Announcement') }}">
        <i class="bi bi-megaphone me-1"></i> {{ $announcement }}
    </div>
@endif
