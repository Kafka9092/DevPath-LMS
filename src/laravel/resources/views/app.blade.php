<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{csrf_token() }}">
    <script>
        (function () {
            const stored = localStorage.getItem('devpath-theme') || 'system';
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const isDark = stored === 'dark' || (stored === 'system' && prefersDark);

            if (isDark) {
                document.documentElement.classList.add('dark');
            }

            document.documentElement.dataset.theme = stored;
            document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="bg-white text-slate-900 antialiased font-sans dark:bg-gray-950 dark:text-gray-100">
    @inertia
</body>
</html>
