@extends('layouts.panel')

@section('title', 'Hesabatlar')

@php
    use App\Enums\AppointmentStatus;
    use App\Support\Money;
    $maxBarber = max(1, (float) $report['byBarber']->max('revenue'));
    $maxService = max(1, (float) $report['byService']->max('revenue'));
@endphp

@section('content')
    <div class="mx-auto max-w-6xl">
        <x-panel.page-header title="Hesabatlar"
                             description="Gəlir yalnız «Tamamlanıb» statuslu rezervasiyalardan, rezervasiya anındakı qiymətlə (snapshot) hesablanır." />

        {{-- ── Dövr filtri ─────────────────────────────────── --}}
        <form method="GET" class="card mb-6 flex flex-wrap items-end gap-3 p-4">
            <div>
                <label for="from" class="field-label">Başlanğıc</label>
                <input id="from" type="date" name="from" value="{{ $from->toDateString() }}" class="field-input">
            </div>
            <div>
                <label for="to" class="field-label">Son</label>
                <input id="to" type="date" name="to" value="{{ $to->toDateString() }}" class="field-input">
                @error('to') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="barber_id" class="field-label">Bərbər</label>
                <select id="barber_id" name="barber_id" class="field-input">
                    <option value="">Hamısı</option>
                    @foreach ($barbers as $b)
                        <option value="{{ $b->id }}" @selected($barberId == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn-primary">Göstər</button>

            <div class="flex w-full flex-wrap gap-2 border-t border-ink-100 pt-3">
                @foreach ($presets as $label => [$pFrom, $pTo])
                    <a href="{{ route('panel.reports', array_filter(['from' => $pFrom->toDateString(), 'to' => $pTo->toDateString(), 'barber_id' => $barberId])) }}"
                       class="rounded-full border border-ink-200 px-3 py-1 text-xs font-medium text-ink-600 hover:border-ink-400 hover:text-ink-900">{{ $label }}</a>
                @endforeach
            </div>
        </form>

        {{-- ── Əsas göstəricilər ───────────────────────────── --}}
        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <x-panel.stat label="Gəlir" :value="Money::format($report['total'])" icon="wallet" :hint="$from->format('d.m.Y').' – '.$to->format('d.m.Y')" />
            <x-panel.stat label="Tamamlanmış ziyarət" :value="$report['completedCount']" icon="check" />
            <x-panel.stat label="Orta çek" :value="Money::format(round($report['averageTicket'], 2))" icon="scissors" />
            <x-panel.stat label="Gözlənilən" :value="Money::format($report['expected'])" icon="clock" hint="aktiv, hələ baş tutmamış" />
        </div>

        {{-- Status bölgüsü --}}
        <div class="card mt-4 flex flex-wrap gap-x-6 gap-y-2 px-5 py-3 text-sm">
            @foreach (AppointmentStatus::cases() as $s)
                <span class="flex items-center gap-2"><x-panel.appointment-status :status="$s" /> <span class="tabular-nums">{{ $report['statusCounts'][$s->value] }}</span></span>
            @endforeach
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            {{-- Bərbər üzrə --}}
            <section class="card">
                <h2 class="border-b border-ink-100 px-5 py-4 font-semibold">Bərbər üzrə gəlir</h2>
                @forelse ($report['byBarber'] as $row)
                    <div class="border-b border-ink-100 px-5 py-3 text-sm last:border-0">
                        <div class="flex justify-between gap-3">
                            <span class="font-medium">{{ $row->name }}</span>
                            <span class="tabular-nums"><span class="text-ink-500">{{ $row->visits }} ziyarət ·</span> <span class="font-semibold">{{ Money::format($row->revenue) }}</span></span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-ink-100" aria-hidden="true">
                            <div class="h-full rounded-full bg-brass-500" style="width: {{ round($row->revenue / $maxBarber * 100, 1) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-ink-500">Bu dövrdə tamamlanmış rezervasiya yoxdur.</p>
                @endforelse
            </section>

            {{-- Xidmət üzrə --}}
            <section class="card">
                <h2 class="border-b border-ink-100 px-5 py-4 font-semibold">Xidmət üzrə gəlir</h2>
                @forelse ($report['byService'] as $row)
                    <div class="border-b border-ink-100 px-5 py-3 text-sm last:border-0">
                        <div class="flex justify-between gap-3">
                            <span class="font-medium">{{ $row->service_name }}</span>
                            <span class="tabular-nums"><span class="text-ink-500">{{ $row->visits }} ·</span> <span class="font-semibold">{{ Money::format($row->revenue) }}</span></span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-ink-100" aria-hidden="true">
                            <div class="h-full rounded-full bg-ink-700" style="width: {{ round($row->revenue / $maxService * 100, 1) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-ink-500">Bu dövrdə tamamlanmış rezervasiya yoxdur.</p>
                @endforelse
            </section>
        </div>
    </div>
@endsection
