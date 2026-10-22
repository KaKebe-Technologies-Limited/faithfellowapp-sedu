<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureApiUserActive;
use App\Http\Middleware\EnsureAppFeature;
use App\Http\Middleware\EnsureChurchPermission;
use App\Http\Middleware\EnsureChurchStaff;
use App\Http\Middleware\EnsureSubscribed;
use App\Http\Middleware\ResolveCustomDomain;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Support\ServerErrors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

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

        $middleware->append(SecurityHeaders::class);

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
            'api.active' => EnsureApiUserActive::class,
            'feature' => EnsureAppFeature::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The API always answers in JSON, even when a client forgets the Accept header.
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());

        // An unexpected server error leaves a note in Super admin → Audit logs ("error.server") and gives the person a short reference to quote.
        $exceptions->report(function (Throwable $e) {
            ServerErrors::note($e);
        });
        $exceptions->respond(function (SymfonyResponse $response, Throwable $e, Request $request) {
            $reference = $request->attributes->get('error_reference');
            if ($reference && $response instanceof JsonResponse && $response->getStatusCode() >= 500) {
                $response->setData([...(array) $response->getData(true), 'reference' => $reference]);
            }

            return $response;
        });
    })->create();
