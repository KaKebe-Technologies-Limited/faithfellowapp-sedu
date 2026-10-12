<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureChurchPermission;
use App\Http\Middleware\EnsureChurchStaff;
use App\Http\Middleware\EnsureSubscribed;
use App\Http\Middleware\ResolveCustomDomain;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Cloudflare / a load balancer in production.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            ResolveCustomDomain::class,
            SetLocale::class,
            EnsureAccountActive::class,
        ]);

        $middleware->encryptCookies(except: ['ff_lite']);

        // Payment gateways, RTMP server, SMS/USSD providers post here; each verifies its own signature.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'church.staff' => EnsureChurchStaff::class,
            'church.can' => EnsureChurchPermission::class,
            'subscribed' => EnsureSubscribed::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
