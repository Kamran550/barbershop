@extends('layouts.panel')

@section('title', 'Ana səhifə')

@php
    use App\Support\Money;
    $tz = $now->getTimezone();
    $month = Str::ucfirst($now->locale('az')->translatedFormat('F'));
@endphp

@section('content')
    <div class="mx-auto max-w-6xl">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm text-ink-500">{{ Str::ucfirst($now->locale('az')->translatedFormat('l, j F Y')) }}</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">Xoş gəldiniz, {{ $user->name }}</h1>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('panel.calendar', ['view' => 'day']) }}" class="btn-ghost border border-ink-200">
                    <i data-lucide="calendar-days" class="size-4"></i> Calendar
                </a>
                @can('create', App\Models\Appointment::class)
                    <a href="{{ route('panel.appointments.create') }}" class="btn-primary">
                        <i data-lucide="plus" class="size-4"></i> Rezervasiya
                    </a>
                @endcan
            </div>
        </div>

        <x-panel.flash class="mt-6" />

        {{-- ── Göstəricilər ─────────────────────────────────── --}}
        <div class="mt-6 grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3 xl:grid-cols-6">
            <x-panel.stat label="Bu gün" :value="$todayCount" icon="calendar" hint="aktiv rezervasiya"
                          :href="route('panel.calendar', ['view' => 'day'])" />
            <x-panel.stat label="Gələcək" :value="$upcomingCount" icon="clock" hint="aktiv rezervasiya"
                          :href="route('panel.appointments.index', ['period' => 'upcoming'])" />
            <x-panel.stat label="Tamamlanıb" :value="$monthCompleted" icon="check" :hint="$month.' ayı'"
                          :href="route('panel.appointments.index', ['status' => 'completed', 'period' => 'month'])" />
            <x-panel.stat label="Ləğv edilib" :value="$monthCancelled" icon="x" :hint="$month.' ayı'"
                          :href="route('panel.appointments.index', ['status' => 'cancelled', 'period' => 'month'])" />
            <x-panel.stat label="Gəlir" :value="Money::format($monthRevenue)" icon="wallet" :hint="$month.' · tamamlanmış'" />
            <x-panel.stat label="Müştərilər" :value="$customerCount" icon="users" hint="ümumi"
                          :href="route('panel.customers.index')" />
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_22rem]">
            {{-- ── Bugünkü gündəlik ─────────────────────────── --}}
            <section class="card">
                <div class="flex items-center justify-between border-b border-ink-100 px-5 py-4">
                    <h2 class="font-semibold">Bugünkü gündəlik</h2>
                    <a href="{{ route('panel.calendar', ['view' => 'day']) }}" class="text-sm text-brass-700 hover:text-brass-600">Calendar-da aç</a>
                </div>
                @forelse ($agenda as $a)
                    <a href="{{ route('panel.appointments.show', $a) }}"
                       class="flex items-center gap-4 border-b border-ink-100 px-5 py-3 last:border-0 hover:bg-ink-100/40">
                        <span class="w-12 shrink-0 font-semibold tabular-nums">{{ $a->starts_at->setTimezone($tz)->format('H:i') }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ $a->customer->name }}</span>
                            <span class="block truncate text-sm text-ink-500">
                                {{ $a->service_name }}@if ($user->isManager()) · {{ $a->barber->name }}@endif
                            </span>
                        </span>
                        <x-panel.appointment-status :status="$a->status" />
                    </a>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-ink-500">Bu gün üçün rezervasiya yoxdur.</p>
                @endforelse
            </section>

            <div class="space-y-6">
                {{-- ── Təsdiq gözləyənlər ───────────────────── --}}
                @if ($pending->isNotEmpty())
                    <section class="card border-amber-200">
                        <h2 class="border-b border-ink-100 px-5 py-4 font-semibold">Təsdiq gözləyir</h2>
                        @foreach ($pending as $a)
                            <a href="{{ route('panel.appointments.show', $a) }}" class="flex items-center justify-between gap-3 border-b border-ink-100 px-5 py-3 text-sm last:border-0 hover:bg-ink-100/40">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{ $a->customer->name }}</span>
                                    <span class="text-ink-500">{{ $a->starts_at->setTimezone($tz)->format('d.m H:i') }} · {{ $a->barber->name }}</span>
                                </span>
                                <i data-lucide="chevron-right" class="size-4 text-ink-400"></i>
                            </a>
                        @endforeach
                    </section>
                @endif

                {{-- ── Bərbərlər üzrə (yalnız menecer) ──────── --}}
                @if ($barberStats->isNotEmpty())
                    <section class="card">
                        <h2 class="border-b border-ink-100 px-5 py-4 font-semibold">Bərbərlər · {{ $month }}</h2>
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs text-ink-500">
                                <tr>
                                    <th class="px-5 py-2 font-medium">Bərbər</th>
                                    <th class="px-2 py-2 text-right font-medium" title="Bu gün">Bu gün</th>
                                    <th class="px-2 py-2 text-right font-medium" title="Tamamlanmış">Tam.</th>
                                    <th class="px-5 py-2 text-right font-medium">Gəlir</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100">
                                @foreach ($barberStats as $b)
                                    <tr @class(['text-ink-400' => ! $b->is_active])>
                                        <td class="px-5 py-2.5 font-medium">{{ $b->name }}</td>
                                        <td class="px-2 py-2.5 text-right tabular-nums">{{ $b->today_count }}</td>
                                        <td class="px-2 py-2.5 text-right tabular-nums">
                                            {{ $b->completed_count }}
                                            @if ($b->no_show_count)<span class="text-xs text-red-600" title="Gəlmədi"> / {{ $b->no_show_count }}</span>@endif
                                        </td>
                                        <td class="px-5 py-2.5 text-right font-medium tabular-nums">{{ Money::format($b->revenue ?? 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </section>
                @endif
            </div>
        </div>
    </div>
@endsection
