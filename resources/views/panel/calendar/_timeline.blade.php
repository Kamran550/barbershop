{{--
    Gün / həftə görünüşü: sütunlar (bərbərlər və ya günlər) × saat şkalası.
    Hər element mütləq mövqe ilə yerləşdirilir: top = (dəqiqə − başlanğıc) × miqyas.
--}}
@php
    use App\Enums\AppointmentStatus as S;

    [$hourFrom, $hourTo] = $hours;
    $scale = 1.2; // px / dəqiqə
    $height = ($hourTo - $hourFrom) * 60 * $scale;

    // Sütunun günündə verilmiş anın piksel mövqeyi (görünən aralığa sıxılmış)
    $y = function ($moment, $day) use ($hourFrom, $hourTo, $scale, $tz) {
        $minutes = ($moment->getTimestamp() - $day->setTimezone($tz)->startOfDay()->getTimestamp()) / 60;

        return max(0, min(($hourTo - $hourFrom) * 60, $minutes - $hourFrom * 60)) * $scale;
    };

    $tone = fn (S $s) => match ($s) {
        S::Pending => 'border-amber-400 bg-amber-50 text-amber-950',
        S::Confirmed => 'border-sky-500 bg-sky-50 text-sky-950',
        S::Completed => 'border-emerald-500 bg-emerald-50 text-emerald-950',
        S::NoShow => 'border-red-400 bg-red-50 text-red-950 line-through decoration-red-300',
        default => 'border-ink-300 bg-ink-100 text-ink-600',
    };
    $now = now();
@endphp

<div class="card overflow-x-auto">
    <div class="grid min-w-fit" style="grid-template-columns: 3.5rem repeat({{ $columns->count() }}, minmax(9rem, 1fr))">
        {{-- Başlıqlar --}}
        <div class="sticky left-0 z-20 border-b border-ink-100 bg-paper-raised"></div>
        @foreach ($columns as $c)
            <div @class(['border-b border-l border-ink-100 px-3 py-2.5 text-sm font-semibold', 'text-brass-700' => $c['date']->isSameDay(now($tz))])>
                {{ $c['title'] }}
                @unless ($c['window'])
                    <span class="block text-xs font-normal text-ink-400">İşləmir</span>
                @endunless
            </div>
        @endforeach

        {{-- Saat şkalası --}}
        <div class="sticky left-0 z-10 bg-paper-raised" style="height: {{ $height }}px">
            @for ($h = $hourFrom; $h < $hourTo; $h++)
                <div class="relative text-right text-[11px] text-ink-400 tabular-nums" style="height: {{ 60 * $scale }}px">
                    {{-- İlk saat etiketi yuxarıdan kəsilməsin --}}
                    <span @class(['absolute right-2', '-top-2' => $h > $hourFrom, 'top-0.5' => $h === $hourFrom])>{{ sprintf('%02d:00', $h) }}</span>
                </div>
            @endfor
        </div>

        @foreach ($columns as $c)
            @php $day = $c['date']; @endphp
            <div class="relative border-l border-ink-100 bg-ink-100/60" style="height: {{ $height }}px">
                {{-- İş saatları (ağ fon) --}}
                @if ($c['window'])
                    <div class="absolute inset-x-0 bg-paper-raised"
                         style="top: {{ $y($c['window']->start, $day) }}px; height: {{ $y($c['window']->end, $day) - $y($c['window']->start, $day) }}px"></div>
                @endif

                {{-- Saat xətləri --}}
                @for ($h = 1; $h < $hourTo - $hourFrom; $h++)
                    <div class="pointer-events-none absolute inset-x-0 border-t border-ink-100" style="top: {{ $h * 60 * $scale }}px"></div>
                @endfor

                @foreach ($c['breaks'] as $br)
                    <div class="cal-break absolute inset-x-0 flex items-center justify-center text-[11px] text-ink-500"
                         style="top: {{ $y($br->start, $day) }}px; height: {{ $y($br->end, $day) - $y($br->start, $day) }}px">Fasilə</div>
                @endforeach

                @foreach ($c['blocks'] as $bl)
                    <div class="cal-block absolute inset-x-0 overflow-hidden px-2 pt-1 text-[11px] text-red-800"
                         style="top: {{ $y($bl->starts_at, $day) }}px; height: {{ max(14, $y($bl->ends_at, $day) - $y($bl->starts_at, $day)) }}px"
                         title="{{ $bl->reason }}">
                        {{ $bl->barber_id ? 'Bloklanıb' : 'Berberxana bağlıdır' }}@if ($bl->reason) · {{ $bl->reason }}@endif
                    </div>
                @endforeach

                {{-- Rezervasiyalar --}}
                @foreach ($c['appointments'] as $a)
                    @php
                        $top = $y($a->starts_at, $day);
                        $h = max(28, $y($a->ends_at, $day) - $top - 2);
                    @endphp
                    <a href="{{ route('panel.appointments.show', $a) }}"
                       class="absolute inset-x-1 overflow-hidden rounded-md border-l-4 px-2 py-1 text-xs shadow-sm transition hover:z-10 hover:shadow-md {{ $tone($a->status) }}"
                       style="top: {{ $top + 1 }}px; height: {{ $h }}px"
                       title="{{ $a->starts_at->setTimezone($tz)->format('H:i') }} {{ $a->customer->name }} — {{ $a->service_name }} ({{ $a->status->label() }})">
                        <span class="font-semibold tabular-nums">{{ $a->starts_at->setTimezone($tz)->format('H:i') }}</span>
                        <span class="font-medium">{{ $a->customer->name }}</span>
                        <span class="block truncate opacity-75">{{ $a->service_name }}</span>
                    </a>
                @endforeach

                {{-- İndiki an --}}
                @if ($day->isSameDay($now->setTimezone($tz)))
                    <div class="pointer-events-none absolute inset-x-0 z-10 border-t-2 border-red-500" style="top: {{ $y($now, $day) }}px">
                        <span class="absolute -top-1.5 -left-1 size-3 rounded-full bg-red-500"></span>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
