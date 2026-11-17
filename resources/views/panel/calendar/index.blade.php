@extends('layouts.panel')

@section('title', 'Calendar')

@php
    use App\Support\Money;
    $views = ['day' => 'Gün', 'week' => 'Həftə', 'month' => 'Ay'];
    $query = fn (array $extra = []) => array_filter([
        'view' => $view,
        'date' => $date->toDateString(),
        'barber_id' => $selected?->id,
        ...$extra,
    ], fn ($v) => $v !== null);
@endphp

@section('content')
    <div class="mx-auto max-w-7xl">
        {{-- ── Başlıq və naviqasiya ───────────────────────────── --}}
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2">
                <a href="{{ route('panel.calendar', $query(['date' => $nav['prev']])) }}" class="btn-ghost border border-ink-200 px-2.5" aria-label="Əvvəlki">
                    <i data-lucide="chevron-left" class="size-4"></i>
                </a>
                <a href="{{ route('panel.calendar', $query(['date' => null])) }}" class="btn-ghost border border-ink-200 whitespace-nowrap">Bu gün</a>
                <a href="{{ route('panel.calendar', $query(['date' => $nav['next']])) }}" class="btn-ghost border border-ink-200 px-2.5" aria-label="Növbəti">
                    <i data-lucide="chevron-right" class="size-4"></i>
                </a>
                <h1 class="ml-2 text-lg font-semibold tracking-tight sm:text-xl">{{ $nav['label'] }}</h1>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($isManager)
                    <form method="GET" class="contents">
                        <input type="hidden" name="view" value="{{ $view }}">
                        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                        <select name="barber_id" class="field-input w-auto py-2" onchange="this.form.submit()" aria-label="Bərbər">
                            <option value="">{{ $view === 'week' ? 'Bərbər seçin' : 'Bütün bərbərlər' }}</option>
                            @foreach ($barbers as $b)
                                <option value="{{ $b->id }}" @selected($selected?->id === $b->id)>{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif

                <div class="inline-flex rounded-lg border border-ink-200 bg-paper-raised p-0.5" role="tablist">
                    @foreach ($views as $key => $label)
                        <a href="{{ route('panel.calendar', $query(['view' => $key])) }}" role="tab" aria-selected="{{ $view === $key ? 'true' : 'false' }}"
                           @class(['rounded-md px-3 py-1.5 text-sm font-medium', 'bg-ink-900 text-paper' => $view === $key, 'text-ink-600 hover:text-ink-900' => $view !== $key])>
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                @can('create', App\Models\Appointment::class)
                    <a href="{{ route('panel.appointments.create', ['date' => $date->toDateString(), 'barber_id' => $selected?->id]) }}" class="btn-primary">
                        <i data-lucide="plus" class="size-4"></i> Rezervasiya
                    </a>
                @endcan
            </div>
        </div>

        @if ($view === 'month')
            @include('panel.calendar._month')
        @else
            @if ($view === 'week')
                <p class="mb-3 text-sm text-ink-500">Bərbər: <span class="font-medium text-ink-900">{{ $weekBarber->name }}</span></p>
            @endif
            @include('panel.calendar._timeline')
        @endif

        {{-- Leqenda --}}
        <div class="mt-4 flex flex-wrap gap-4 text-xs text-ink-500">
            <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-amber-100 ring-1 ring-amber-300"></span> Gözləmədə</span>
            <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-sky-100 ring-1 ring-sky-300"></span> Təsdiqlənib</span>
            <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-emerald-100 ring-1 ring-emerald-300"></span> Tamamlanıb</span>
            <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-red-100 ring-1 ring-red-300"></span> Gəlmədi</span>
            @if ($view !== 'month')
                <span class="flex items-center gap-1.5"><span class="cal-break size-3 rounded"></span> Fasilə</span>
                <span class="flex items-center gap-1.5"><span class="cal-block size-3 rounded"></span> Bloklanıb</span>
                <span class="flex items-center gap-1.5"><span class="size-3 rounded bg-ink-100"></span> İş saatından kənar</span>
            @endif
        </div>
    </div>
@endsection
