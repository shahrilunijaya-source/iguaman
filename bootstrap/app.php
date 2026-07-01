<?php

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Auth routes are named system.login (no default `login` route).
        $middleware->redirectGuestsTo(fn () => route('system.login'));

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Security headers on every web response; force migrated users to reset their password.
        $middleware->web(append: [
            SecurityHeaders::class,
            ForcePasswordChange::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            // LOG-02: record every permission-denied attempt for forensics.
            logger()->warning('authz.denied', [
                'user_id' => optional($request->user())->id,
                'path' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
            ]);

            if ($request->is('api/*')) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
            $user = $request->user();

            if (! $user) {
                return redirect()->route('system.login');
            }

            // Awam (citizen) users hitting a staff/lawyer route get an explicit 403.
            // Staff/lawyer users hitting a gated area redirect to their own landing.
            if ($user->isAwam()) {
                abort(403, 'Akses tidak dibenarkan.');
            }

            return redirect()->route($user->homeRoute());
        });
    })->create();
