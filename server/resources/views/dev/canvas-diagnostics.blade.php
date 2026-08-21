<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Canvas Diagnostics — {{ config('app.name', 'Kinevo') }}</title>
        <script>
            // Render nothing visible if this page leaks to production accidentally.
            if (window.location.hostname !== 'localhost' && !document.body.dataset.devDiagnostics) {
                document.body.innerHTML = 'Diagnostics disabled.';
            }
        </script>
        <style>
            body { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; margin: 2rem; background: #faf9f7; color: #1b1b18; }
            .card { background: #fff; border: 1px solid #d6d3d1; border-radius: 4px; padding: 1rem 1.25rem; margin-bottom: 1rem; max-width: 46rem; }
            h1 { font-size: 1.25rem; margin: 0 0 0.75rem; }
            dl { display: grid; grid-template-columns: 14rem 1fr; gap: 0.35rem 1rem; margin: 0; }
            dt { font-weight: 600; color: #57534e; }
            dd { margin: 0; }
            .ok { color: #15803d; }
            .no { color: #b91c1c; }
            .warn { color: #b45309; }
        </style>
    </head>
    <body data-dev-diagnostics>
        <div class="card">
            <h1>Canvas browser diagnostics (design.md §36)</h1>
            <dl>
                <dt>Environment</dt><dd>{{ $env }}</dd>
                <dt>Database connected</dt><dd class="{{ $dbUp ? 'ok' : 'no' }}">{{ $dbUp ? 'YES' : 'NO' }}</dd>
                <dt>Canvas rows</dt><dd>{{ $canvasCount ?? 'n/a' }}</dd>
                <dt>Browser online</dt><dd class="js-browser-online">—</dd>
                <dt>Service Worker active</dt><dd class="js-sw">—</dd>
                <dt>IndexedDB available</dt><dd class="js-idb">—</dd>
                <dt>React mounted</dt><dd>REPORTED BY APP (dev panel)</dd>
                <dt>Excalidraw mounted</dt><dd>REPORTED BY APP (dev panel)</dd>
                <dt>Initial data loaded</dt><dd>REPORTED BY APP (dev panel)</dd>
                <dt>Scene changes received</dt><dd>REPORTED BY APP (dev panel)</dd>
                <dt>Autosave connected</dt><dd>REPORTED BY APP (dev panel)</dd>
            </dl>
        </div>
        <script>
            (function () {
                const mark = (sel, ok, text) => {
                    const el = document.querySelector(sel);
                    if (el) { el.textContent = text; el.className = ok ? 'ok' : 'no'; }
                };
                mark('.js-browser-online', navigator.onLine, navigator.onLine ? 'YES' : 'NO');
                if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
                    mark('.js-sw', true, 'YES');
                } else if ('serviceWorker' in navigator) {
                    mark('.js-sw', false, 'NO (not controlled yet)');
                } else {
                    mark('.js-sw', false, 'NOT SUPPORTED');
                }
                if ('indexedDB' in window) {
                    mark('.js-idb', true, 'YES');
                } else {
                    mark('.js-idb', false, 'NOT AVAILABLE');
                }
            })();
        </script>
    </body>
</html>
