<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // Security headers on every web response; force migrated users to reset their password.
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\ForcePasswordChange::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, Request $request) {
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
