<?php

namespace App\Providers;

use App\Notifications\Channels\TenantDatabaseChannel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DatabaseChannel::class, TenantDatabaseChannel::class);
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
        Gate::before(fn ($user) => $user->isSuperAdmin() ? true : null);

        $this->configureRateLimiting();

        Blade::directive('money', fn ($expr) => "<?php echo e(ff_money($expr)); ?>");
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
