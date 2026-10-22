<?php

namespace App\Providers;

use App\Jobs\SendSignupAlert;
use App\Models\User;
use App\Notifications\Channels\ExpoPushChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\TenantDatabaseChannel;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Services\GoogleSettings;
use App\Services\MailSettings;
use App\View\Composers\AppShellComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        // Every new account is announced to the platform owner by email. Only real sign-ups count: accounts made by seeders, imports and the
        // console stay quiet (the tests switch this on with faithfellows.signup_alerts_in_console).
        User::created(function (User $user) {
            if (! app()->runningInConsole() || config('faithfellows.signup_alerts_in_console')) {
                rescue(fn () => SendSignupAlert::dispatch($user->id)->afterResponse(), report: false);
            }
        });

        // The email account saved by the super admin (Super admin → Email) replaces the server's mail settings. A queue worker that
        // has been running for a while looks again before each job, so a change on the website reaches it without a restart.
        rescue(fn () => MailSettings::apply(), report: false);
        rescue(fn () => GoogleSettings::apply(), report: false);
        Event::listen(JobProcessing::class, fn () => rescue(fn () => MailSettings::apply(), report: false));

        // Production passwords: 8+ chars with letters and numbers, not in a known breach (HIBP k-anonymity).
        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(8)->letters()->numbers()->uncompromised()
            : Password::min(8));

        // Custom notification channels: via() can return 'sms', 'whatsapp', 'webpush'.
        Notification::extend('sms', fn ($app) => $app->make(SmsChannel::class));
        Notification::extend('whatsapp', fn ($app) => $app->make(WhatsAppChannel::class));
        Notification::extend('webpush', fn ($app) => $app->make(WebPushChannel::class));
        Notification::extend('expo', fn ($app) => $app->make(ExpoPushChannel::class));

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
        // Everything under /api/v1. It is counted for the person (their app token), not for the address: many phones share one address (a church's
        // Wi-Fi, a mobile network), and counting them together shut whole congregations out with "Too many attempts". A route that has a limit of its
        // own (a game's one-second polling, sign-in, giving, reactions) is held to that one; counting it here as well would let one game screen use up
        // the whole allowance. People who are not signed in yet share their address's allowance, which is larger than before.
        RateLimiter::for('api', function (Request $request) {
            $own = collect($request->route()?->gatherMiddleware() ?? [])->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:') && $m !== 'throttle:api');
            if ($own) {
                return Limit::none();
            }
            $user = $request->user('sanctum');

            return $user ? Limit::perMinute(240)->by('user:'.$user->id) : Limit::perMinute(120)->by('ip:'.$request->ip());
        });

        RateLimiter::for('contact', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perDay(20)->by($request->ip()),
        ]);

        // Account creation from the app: per minute and per day for an address. A mobile network puts thousands of phones behind one address, so the daily
        // cap is high; it only has to stop a script.
        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(20)->by($request->ip()),
            Limit::perDay(300)->by($request->ip()),
        ]);

        RateLimiter::for('giving', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('engagement', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
    }
}
