@extends('layouts.public')

@php
    use App\Enums\AppointmentStatus;
    use App\Support\Money;
    use App\Support\PhoneNumber;

    $start = $appointment->starts_at->setTimezone($tz);
    $end = $appointment->ends_at->setTimezone($tz);
    $status = $appointment->status;
    $tone = match ($status) {
        AppointmentStatus::Confirmed, AppointmentStatus::Completed => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
        AppointmentStatus::Pending => 'bg-amber-50 text-amber-800 ring-amber-200',
        default => 'bg-ink-100 text-ink-600 ring-ink-200',
    };
@endphp

@section('title', 'Rezervasiyanız · '.$settings->name)

@push('meta')
    {{-- Token URL-dədir: axtarış sistemləri indeksləməsin, keçidlərdə Referer ilə sızmasın --}}
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
@endpush

@section('content')
    <div class="bg-ink-950 py-12 sm:py-16">
        <div class="page-container max-w-xl">

            @if (session('booked'))
                <div class="mb-8 text-center text-paper">
                    <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-brass-400 text-ink-950">
                        <i data-lucide="check" class="size-7"></i>
                    </span>
                    <h1 class="mt-5 font-display text-3xl font-semibold sm:text-4xl">
                        {{ $status === AppointmentStatus::Pending ? 'Sorğunuz qəbul edildi' : 'Rezervasiya təsdiqləndi' }}
                    </h1>
                    <p class="mt-2 text-ink-400">
                        {{ $status === AppointmentStatus::Pending ? 'Təsdiq üçün sizinlə əlaqə saxlayacağıq.' : 'Sizi gözləyirik!' }}
                    </p>
                </div>
            @else
                <h1 class="mb-8 text-center font-display text-3xl font-semibold text-paper sm:text-4xl">Rezervasiyanız</h1>
            @endif

            @if (session('status'))
                <div role="status" class="mb-6 rounded-xl border border-paper/15 bg-paper/5 px-4 py-3 text-center text-sm text-paper">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div role="alert" class="mb-6 rounded-xl border border-red-400/30 bg-red-500/10 px-4 py-3 text-center text-sm text-red-100">{{ session('error') }}</div>
            @endif

            {{-- Bilet --}}
            <article class="overflow-hidden rounded-3xl bg-paper text-ink-900 shadow-2xl shadow-black/40">
                <div class="flex items-start justify-between gap-4 p-6 sm:p-8">
                    <div>
                        <p class="eyebrow text-brass-700">{{ $settings->name }}</p>
                        <p class="mt-3 font-display text-4xl font-semibold tabular-nums">{{ $start->format('H:i') }}</p>
                        <p class="mt-1 text-ink-600">{{ Str::ucfirst($start->locale('az')->translatedFormat('l, j F Y')) }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $tone }}">{{ $status->label() }}</span>
                </div>

                {{-- Bilet kəsik xətti --}}
                <div aria-hidden="true" class="relative h-6">
                    <div class="absolute top-1/2 -left-3 size-6 -translate-y-1/2 rounded-full bg-ink-950"></div>
                    <div class="absolute top-1/2 -right-3 size-6 -translate-y-1/2 rounded-full bg-ink-950"></div>
                    <div class="absolute inset-x-6 top-1/2 border-t-2 border-dashed border-ink-200"></div>
                </div>

                <dl class="grid grid-cols-2 gap-x-6 gap-y-5 p-6 text-sm sm:p-8">
                    <div><dt class="text-ink-500">Xidmət</dt><dd class="mt-1 font-semibold">{{ $appointment->service_name }}</dd></div>
                    <div><dt class="text-ink-500">Usta</dt><dd class="mt-1 font-semibold">{{ $appointment->barber->name }}</dd></div>
                    <div><dt class="text-ink-500">Vaxt</dt><dd class="mt-1 font-semibold">{{ $start->format('H:i') }}–{{ $end->format('H:i') }}</dd></div>
                    <div><dt class="text-ink-500">Qiymət</dt><dd class="mt-1 font-semibold">{{ Money::format($appointment->service_price) }}</dd></div>
                    <div><dt class="text-ink-500">Ad</dt><dd class="mt-1 font-semibold">{{ $appointment->customer->name }}</dd></div>
                    {{-- Telefon maskalanır: link başqasının əlinə keçsə belə nömrə tam görünmür --}}
                    <div><dt class="text-ink-500">Telefon</dt><dd class="mt-1 font-semibold tabular-nums">{{ PhoneNumber::mask($appointment->customer->phone) }}</dd></div>
                </dl>

                @if ($settings->address)
                    <p class="flex items-start gap-2 border-t border-ink-100 px-6 py-4 text-sm text-ink-600 sm:px-8">
                        <i data-lucide="map-pin" class="mt-0.5 size-4 shrink-0 text-brass-600"></i> {{ $settings->address }}
                    </p>
                @endif
            </article>

            {{-- Əməliyyatlar --}}
            <div class="mt-6 space-y-3">
                @if ($status->occupiesSlot())
                    <a href="{{ route('booking.calendar', $token) }}" class="btn-outline-light w-full">
                        <i data-lucide="calendar-plus" class="size-4"></i> Təqvimə əlavə et
                    </a>
                @endif

                @if ($canCancel)
                    <form method="POST" action="{{ route('booking.cancel', $token) }}"
                          onsubmit="return confirm('Rezervasiyanı ləğv etmək istədiyinizə əminsiniz?')">
                        @csrf
                        <button type="submit" class="w-full rounded-full px-6 py-3 text-sm font-semibold text-red-300 transition hover:bg-red-500/10">
                            Rezervasiyanı ləğv et
                        </button>
                    </form>
                @elseif ($status->occupiesSlot())
                    <p class="text-center text-sm text-ink-400">
                        Rezervasiyaya {{ intdiv($settings->cancel_deadline_minutes, 60) }} saatdan az qalıb — ləğv və ya dəyişiklik üçün
                        @if ($settings->phone)
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $settings->phone) }}" class="text-paper underline">{{ $settings->phone }}</a>
                        @else
                            bizimlə
                        @endif
                        əlaqə saxlayın.
                    </p>
                @endif

                @if (session('booked'))
                    <p class="pt-4 text-center text-xs text-ink-500">
                        Bu səhifənin linkini saxlayın — rezervasiyanıza baxmaq və ləğv etmək üçün lazımdır.
                    </p>
                @endif
            </div>
        </div>
    </div>
@endsection
