<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $settings->name)</title>
    <meta name="description" content="@yield('description', $settings->tagline ?? $settings->name)">
    <meta name="theme-color" content="#0e0d0c">
    @stack('meta')
    <meta property="og:title" content="@yield('title', $settings->name)">
    <meta property="og:description" content="@yield('description', $settings->tagline ?? $settings->name)">
    <meta property="og:type" content="website">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=fraunces:500,600,600i&family=inter:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-dvh flex-col bg-paper" x-data="{ nav: false }">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-paper focus:px-4 focus:py-2">Məzmuna keç</a>

    <header class="sticky top-0 z-40 border-b border-paper/10 bg-ink-950/95 backdrop-blur">
        <div class="page-container flex h-16 items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 text-paper">
                <span class="flex size-9 items-center justify-center rounded-full border border-brass-400/50 text-brass-400">
                    <i data-lucide="scissors" class="size-4"></i>
                </span>
                <span class="font-display text-xl font-semibold tracking-tight">{{ $settings->name }}</span>
            </a>

            <nav class="hidden items-center gap-8 text-sm text-ink-300 md:flex" aria-label="Əsas menyu">
                <a href="{{ route('home') }}#xidmetler" class="hover:text-paper">Xidmətlər</a>
                <a href="{{ route('home') }}#komanda" class="hover:text-paper">Komanda</a>
                <a href="{{ route('home') }}#elaqe" class="hover:text-paper">Əlaqə</a>
            </nav>

            <div class="flex items-center gap-2">
                <a href="{{ route('booking.create') }}" class="btn-brass px-5 py-2.5">Rezervasiya</a>
                <button type="button" class="rounded-full p-2 text-ink-300 hover:text-paper md:hidden" @click="nav = !nav"
                        :aria-expanded="nav" aria-controls="mobile-nav" aria-label="Menyu">
                    <i data-lucide="menu" class="size-5"></i>
                </button>
            </div>
        </div>

        <nav id="mobile-nav" x-cloak x-show="nav" x-transition.opacity @click.outside="nav = false"
             class="border-t border-paper/10 bg-ink-950 md:hidden" aria-label="Mobil menyu">
            <div class="page-container flex flex-col py-3 text-ink-200">
                <a href="{{ route('home') }}#xidmetler" @click="nav = false" class="py-3">Xidmətlər</a>
                <a href="{{ route('home') }}#komanda" @click="nav = false" class="py-3">Komanda</a>
                <a href="{{ route('home') }}#elaqe" @click="nav = false" class="py-3">Əlaqə</a>
            </div>
        </nav>
    </header>

    <main id="main" class="flex-1">
        @yield('content')
    </main>

    <footer class="bg-ink-950 text-ink-400">
        <div class="page-container flex flex-col gap-6 py-10 text-sm sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2.5 text-paper">
                <i data-lucide="scissors" class="size-4 text-brass-400"></i>
                <span class="font-display text-lg">{{ $settings->name }}</span>
            </div>
            <div class="flex flex-wrap gap-x-6 gap-y-2">
                @if ($settings->address)<span>{{ $settings->address }}</span>@endif
                @if ($settings->phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $settings->phone) }}" class="hover:text-paper">{{ $settings->phone }}</a>@endif
                <span>© {{ now()->year }}</span>
            </div>
        </div>
    </footer>
</body>
</html>
