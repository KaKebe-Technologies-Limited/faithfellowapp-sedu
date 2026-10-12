<?php

namespace App\Providers;

use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\TenantDatabaseChannel;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\View\Composers\AppShellComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DatabaseChannel::class, TenantDatabaseChannel::class);
        $this->app->singleton(AppShellComposer::class);
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Enforce eager loading during development without breaking pages.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation) {
            Log::channel('single')->debug('Lazy loading '.$relation.' on '.$model::class);
        });

        // Super admins pass every authorization check.
        // …except confidential counseling notes, which platform staff never read.
        Gate::before(fn ($user, string $ability) => $user->isSuperAdmin() && $ability !== 'viewNotes' ? true : null);

        $this->configureRateLimiting();

        // Custom notification channels: via() can return 'sms', 'whatsapp', 'webpush'.
        Notification::extend('sms', fn ($app) => $app->make(SmsChannel::class));
        Notification::extend('whatsapp', fn ($app) => $app->make(WhatsAppChannel::class));
        Notification::extend('webpush', fn ($app) => $app->make(WebPushChannel::class));

        Blade::directive('money', fn ($expr) => "<?php echo e(ff_money($expr)); ?>");

        View::composer([
            'components.layouts.app-shell',
            'components.layouts.member',
            'components.layouts.church-admin',
            'components.layouts.super-admin',
        ], AppShellComposer::class);
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('contact', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perDay(20)->by($request->ip()),
        ]);

        RateLimiter::for('giving', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('engagement', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
    }
}
