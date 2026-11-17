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
@php
    $user = auth()->user();
    // Menyu rola görə qurulur. 'active' — hansı route-larda bənd seçilmiş görünür.
    $nav = [['route' => 'panel.dashboard', 'active' => 'panel.dashboard', 'label' => 'Ana səhifə', 'icon' => 'layout-dashboard']];

    if ($user->isManager()) {
        $nav[] = ['route' => 'panel.calendar', 'active' => 'panel.calendar', 'label' => 'Calendar', 'icon' => 'calendar-days'];
        $nav[] = ['route' => 'panel.appointments.index', 'active' => 'panel.appointments.*', 'label' => 'Rezervasiyalar', 'icon' => 'calendar-check'];
        $nav[] = ['route' => 'panel.customers.index', 'active' => 'panel.customers.*', 'label' => 'Müştərilər', 'icon' => 'contact'];
        $nav[] = ['route' => 'panel.barbers.index', 'active' => 'panel.barbers.*', 'label' => 'Bərbərlər', 'icon' => 'users'];
        // $nav[] = ['route' => 'panel.services.index', 'active' => 'panel.services.*', 'label' => 'Xidmətlər', 'icon' => 'scissors'];
        $nav[] = ['route' => 'panel.blocked-periods.index', 'active' => 'panel.blocked-periods.*', 'label' => 'Bloklanmış vaxtlar', 'icon' => 'ban'];
        $nav[] = ['route' => 'panel.reports', 'active' => 'panel.reports', 'label' => 'Hesabatlar', 'icon' => 'chart-column'];
        $nav[] = ['route' => 'panel.settings.edit', 'active' => 'panel.settings.*', 'label' => 'Parametrlər', 'icon' => 'settings'];
    } elseif ($user->barberId()) {
        // Bərbər öz işini özü idarə edir
        $nav[] = ['route' => 'panel.calendar', 'active' => 'panel.calendar', 'label' => 'Calendar', 'icon' => 'calendar-days'];
        $nav[] = ['route' => 'panel.appointments.index', 'active' => 'panel.appointments.*', 'label' => 'Rezervasiyalarım', 'icon' => 'calendar-check'];
        $nav[] = ['route' => 'panel.customers.index', 'active' => 'panel.customers.*', 'label' => 'Müştərilərim', 'icon' => 'contact'];
        $nav[] = ['route' => 'panel.services.index', 'active' => 'panel.services.*', 'label' => 'Xidmətlərim', 'icon' => 'scissors'];
        $nav[] = ['route' => 'panel.my-schedule', 'active' => ['panel.my-schedule', 'panel.barbers.schedule'], 'label' => 'Qrafikim', 'icon' => 'clock'];
        $nav[] = ['route' => 'panel.blocked-periods.index', 'active' => 'panel.blocked-periods.*', 'label' => 'Bloklanmış vaxtlar', 'icon' => 'ban'];
        $nav[] = ['route' => 'panel.profile.edit', 'active' => 'panel.profile.*', 'label' => 'Profilim', 'icon' => 'user'];
    }
@endphp
<body class="min-h-dvh" x-data="{ menu: false }">
    <div class="lg:grid lg:min-h-dvh lg:grid-cols-[15rem_1fr]">

        {{-- Mobil üst panel --}}
        <header class="sticky top-0 z-30 flex items-center justify-between border-b border-ink-800 bg-ink-950 px-4 py-3 lg:hidden">
            <a href="{{ route('panel.dashboard') }}" class="flex items-center gap-2 font-semibold text-paper">
                <i data-lucide="scissors" class="size-5 text-brass-400"></i>
                {{ config('app.name') }}
            </a>
            <button type="button" @click="menu = !menu" class="rounded-lg p-2 text-ink-300 hover:bg-ink-800"
                    :aria-expanded="menu" aria-controls="panel-sidebar" aria-label="Menyu">
                <i data-lucide="menu" class="size-5"></i>
            </button>
        </header>

        {{-- Sidebar --}}
        <aside id="panel-sidebar"
               {{-- data-[open=true] selektorunun specificity-si yüksəkdir, ona görə -translate-x-full-u üstələyir --}}
               class="fixed inset-y-0 left-0 z-40 flex w-60 -translate-x-full flex-col bg-ink-950 text-ink-300 transition-[translate] data-[open=true]:translate-x-0 lg:static lg:translate-x-0"
               :data-open="menu">
            <div class="hidden items-center gap-2.5 px-5 py-5 lg:flex">
                <span class="flex size-8 items-center justify-center rounded-lg bg-brass-500/15 text-brass-400 ring-1 ring-brass-500/30">
                    <i data-lucide="scissors" class="size-4"></i>
                </span>
                <span class="font-semibold text-paper">{{ config('app.name') }}</span>
            </div>

            <nav class="flex-1 space-y-0.5 px-3 py-4 lg:py-2" aria-label="Panel menyusu">
                @foreach ($nav as $item)
                    @php $active = request()->routeIs($item['active']); @endphp
                    <a href="{{ route($item['route']) }}"
                       @class([
                           'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                           'bg-ink-800 text-paper' => $active,
                           'hover:bg-ink-900 hover:text-paper' => ! $active,
                       ])
                       @if ($active) aria-current="page" @endif>
                        <i data-lucide="{{ $item['icon'] }}" @class(['size-4', 'text-brass-400' => $active])></i>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="border-t border-ink-800 p-3">
                <div class="flex items-center gap-3 px-2 py-2">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-ink-800 text-xs font-semibold text-paper">
                        {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-paper">{{ $user->name }}</p>
                        <p class="text-xs text-ink-500">{{ $user->role->label() }}</p>
                    </div>
                    <form method="POST" action="{{ route('panel.logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg p-2 text-ink-400 hover:bg-ink-800 hover:text-paper" title="Çıxış" aria-label="Çıxış">
                            <i data-lucide="log-out" class="size-4"></i>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Mobil menyu açıq olanda fon --}}
        <div x-cloak x-show="menu" @click="menu = false" class="fixed inset-0 z-30 bg-ink-950/60 lg:hidden"></div>

        <main class="min-w-0 px-4 py-6 sm:px-6 lg:px-10 lg:py-10">
            @yield('content')
        </main>
    </div>
</body>
</html>
