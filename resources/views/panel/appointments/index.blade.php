@extends('layouts.panel')

@section('title', 'Rezervasiyalar')

@php
    use App\Enums\AppointmentStatus;
    use App\Support\Money;
    use App\Support\PhoneNumber;
    $periods = ['upcoming' => 'Gələcək', 'today' => 'Bu gün', 'month' => 'Bu ay', 'past' => 'Keçmiş', 'custom' => 'Tarix aralığı'];
@endphp

@section('content')
    <div class="mx-auto max-w-6xl">
        <x-panel.page-header title="Rezervasiyalar">
            <x-slot:actions>
                <a href="{{ route('panel.calendar') }}" class="btn-ghost border border-ink-200">
                    <i data-lucide="calendar-days" class="size-4"></i> Calendar
                </a>
                @can('create', App\Models\Appointment::class)
                    <a href="{{ route('panel.appointments.create') }}" class="btn-primary"><i data-lucide="plus" class="size-4"></i> Yeni</a>
                @endcan
            </x-slot:actions>
        </x-panel.page-header>

        <x-panel.flash />

        {{-- ── Filterlər (GET forma — URL paylaşıla bilər) ── --}}
        <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-[minmax(14rem,1fr)_auto_auto_auto_auto]"
              x-data="{ period: @js($filters['period']) }">
            <div class="relative">
                <label for="q" class="sr-only">Axtarış</label>
                <i data-lucide="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400"></i>
                <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Müştəri adı və ya telefon" class="field-input pl-9">
            </div>

            <select name="period" x-model="period" class="field-input" aria-label="Dövr">
                @foreach ($periods as $value => $label)
                    <option value="{{ $value }}" @selected($filters['period'] === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <select name="status" class="field-input" aria-label="Status">
                <option value="">Bütün statuslar</option>
                @foreach (AppointmentStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(($filters['status'] ?? null) === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>

            @if ($barbers->isNotEmpty())
                <select name="barber_id" class="field-input" aria-label="Bərbər">
                    <option value="">Bütün bərbərlər</option>
                    @foreach ($barbers as $b)
                        <option value="{{ $b->id }}" @selected(($filters['barber_id'] ?? null) == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            @endif

            <button type="submit" class="btn-primary">Filtrlə</button>

            <div x-show="period === 'custom'" x-cloak class="flex flex-wrap items-center gap-2 sm:col-span-2 lg:col-span-5">
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="field-input w-auto" aria-label="Başlanğıc tarix" :disabled="period !== 'custom'">
                <span class="text-ink-400">—</span>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="field-input w-auto" aria-label="Son tarix" :disabled="period !== 'custom'">
                @error('to') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </form>

        @if ($appointments->isEmpty())
            <div class="card px-6 py-16 text-center text-sm text-ink-500">Bu filtrlərə uyğun rezervasiya tapılmadı.</div>
        @else
            <div class="card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[44rem] text-sm">
                        <thead class="border-b border-ink-100 bg-ink-100/40 text-left text-xs text-ink-500">
                            <tr>
                                <th class="px-4 py-2.5 font-medium">Vaxt</th>
                                <th class="px-4 py-2.5 font-medium">Müştəri</th>
                                <th class="px-4 py-2.5 font-medium">Xidmət</th>
                                @if ($barbers->isNotEmpty())<th class="px-4 py-2.5 font-medium">Bərbər</th>@endif
                                <th class="px-4 py-2.5 text-right font-medium">Qiymət</th>
                                <th class="px-4 py-2.5 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach ($appointments as $a)
                                @php $start = $a->starts_at->setTimezone($tz); @endphp
                                <tr class="cursor-pointer hover:bg-ink-100/40" onclick="location.href='{{ route('panel.appointments.show', $a) }}'">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <a href="{{ route('panel.appointments.show', $a) }}" class="font-medium tabular-nums">{{ $start->format('d.m.Y') }}</a>
                                        <span class="block text-ink-500 tabular-nums">{{ $start->format('H:i') }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="block font-medium">{{ $a->customer->name }}</span>
                                        <span class="block text-ink-500 tabular-nums">{{ PhoneNumber::format($a->customer->phone) }}</span>
                                    </td>
                                    <td class="px-4 py-3">{{ $a->service_name }}</td>
                                    @if ($barbers->isNotEmpty())<td class="px-4 py-3">{{ $a->barber->name }}</td>@endif
                                    <td class="px-4 py-3 text-right tabular-nums">{{ Money::format($a->service_price) }}</td>
                                    <td class="px-4 py-3"><x-panel.appointment-status :status="$a->status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <x-panel.pagination :paginator="$appointments" />
        @endif
    </div>
@endsection
