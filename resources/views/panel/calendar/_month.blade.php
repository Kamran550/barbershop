{{-- Ay görünüşü: hər gün üçün aktiv rezervasiya sayı və tamamlanmış gəlir. Günə klik → gün görünüşü. --}}
<div class="card overflow-hidden">
    <div class="grid grid-cols-7 border-b border-ink-100 bg-ink-100/40 text-center text-xs font-medium text-ink-500">
        @foreach (\App\Support\Weekday::SHORT as $short)
            <div class="py-2">{{ $short }}</div>
        @endforeach
    </div>
    <div class="grid grid-cols-7">
        @foreach ($monthDays as $d)
            <a href="{{ route('panel.calendar', array_filter(['view' => 'day', 'date' => $d['date']->toDateString(), 'barber_id' => $selected?->id])) }}"
               @class([
                   'flex min-h-20 flex-col border-b border-l border-ink-100 p-2 text-sm transition hover:bg-ink-100/40 sm:min-h-24',
                   'bg-ink-100/30 text-ink-400' => ! $d['inMonth'],
               ])>
                <span @class([
                    'flex size-7 items-center justify-center rounded-full text-sm tabular-nums',
                    'bg-ink-900 font-semibold text-paper' => $d['date']->isSameDay(now($tz)),
                ])>{{ $d['date']->day }}</span>
                @if ($d['count'])
                    <span class="mt-auto text-xs font-medium text-ink-700">{{ $d['count'] }} rez.</span>
                    @if ($d['revenue'] > 0)
                        <span class="text-xs text-emerald-700 tabular-nums">{{ \App\Support\Money::format($d['revenue']) }}</span>
                    @endif
                @endif
            </a>
        @endforeach
    </div>
</div>
