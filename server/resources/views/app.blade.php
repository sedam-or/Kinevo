<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Kinevo') }}</title>
        <script>
            // Pre-hydration theme (TASK-P17-013): resolve the stored/system
            // preference before first paint so dark users never see a light
            // flash. Keep in sync with resources/js/shell/theme.ts.
            (function () {
                try {
                    var p = localStorage.getItem('kinevo.theme');
                    var dark =
                        p === 'dark' ||
                        ((p === null || p === 'system') &&
                            window.matchMedia &&
                            window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', !!dark);
                } catch (e) {}
            })();
        </script>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
