<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-ink-950">
    <div class="relative flex min-h-dvh items-center justify-center overflow-hidden px-4 py-12">
        {{-- Fon: bərbər dirəyi zolaqlarına işarə edən çox zəif diaqonal xətlər --}}
        <div aria-hidden="true" class="pointer-events-none absolute inset-0 opacity-[0.05]"
             style="background-image: repeating-linear-gradient(135deg, var(--color-brass-400) 0 2px, transparent 2px 28px)"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -top-40 left-1/2 size-[36rem] -translate-x-1/2 rounded-full bg-brass-500/10 blur-3xl"></div>

        <main class="relative w-full max-w-sm">
            @yield('content')
        </main>
    </div>
</body>
</html>
