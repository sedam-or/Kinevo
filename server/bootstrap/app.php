<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // End-of-Day Reconciliation (FR-47): prompt at 21:00, deadline at 23:59
        // local time. Idempotent via the Task state machine.
        $schedule->command('eod:reconcile --phase=prompt')->dailyAt('21:00');
        $schedule->command('eod:reconcile --phase=deadline')->dailyAt('23:59');

        // Holiday-end notification (FR-39/FR-41): H-3 before an active break
        // ends, exactly once per break period.
        $schedule->command('break:notify-end')->dailyAt('20:30');

        // Weekly planning trigger (ADR-016 §2.1): the pass runs daily, but a
        // draft is only prepared for users whose LOCAL week just started
        // (profile timezone). Persisted draft, never auto-applied;
        // idempotent per (user, week anchor); per-user run lock.
        $schedule->command('schedule:prepare-weekly')->dailyAt('04:00')->withoutOverlapping();

        // Offline operation ledger retention (ADR-017 §2.5): bounded ledger
        // growth; safe replays are short-lived because clients remove applied
        // items immediately after reconciliation.
        $schedule->command('offline:prune-ledger')->dailyAt('03:00');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request): ?string {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return route('login');
        });

        // The app only ever listens on the internal compose network behind the
        // reverse proxy (TASK-081). Trust forwarded headers so HTTPS URLs and
        // schemes are generated correctly (SRS NFR-02).
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
