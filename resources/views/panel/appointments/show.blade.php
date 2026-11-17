@extends('layouts.panel')

@section('title', 'Rezervasiya #'.$appointment->id)

@php
    use App\Enums\AppointmentStatus as S;
    use App\Support\Money;
    use App\Support\PhoneNumber;

    $start = $appointment->starts_at->setTimezone($tz);
    $end = $appointment->ends_at->setTimezone($tz);
    $status = $appointment->status;
    $started = $appointment->starts_at->isPast();
    $canUpdate = auth()->user()->can('updateStatus', $appointment);
@endphp

@section('content')
    <div class="mx-auto max-w-4xl">
        <x-panel.page-header :title="$appointment->customer->name" :back="route('panel.appointments.index')">
            <x-slot:actions>
                <x-panel.appointment-status :status="$status" class="px-3 py-1 text-sm" />
            </x-slot:actions>
        </x-panel.page-header>

        <x-panel.flash />

        <div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
            <section class="card p-5 sm:p-6">
                <dl class="grid gap-x-6 gap-y-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm text-ink-500">Tarix</dt>
                        <dd class="mt-1 font-medium">{{ Str::ucfirst($start->locale('az')->translatedFormat('l, j F Y')) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-500">Vaxt</dt>
                        <dd class="mt-1 font-medium tabular-nums">{{ $start->format('H:i') }}–{{ $end->format('H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-500">Xidmət</dt>
                        <dd class="mt-1 font-medium">{{ $appointment->service_name }} · {{ $appointment->service_duration }} dəq</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-500">Qiymət</dt>
                        <dd class="mt-1 font-medium">{{ Money::format($appointment->service_price) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-500">Bərbər</dt>
                        <dd class="mt-1 font-medium">{{ $appointment->barber->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-500">Müştəri</dt>
                        <dd class="mt-1">
                            <a href="{{ route('panel.customers.show', $appointment->customer) }}" class="font-medium hover:underline">{{ $appointment->customer->name }}</a>
                            <a href="tel:{{ $appointment->customer->phone }}" class="block text-sm text-ink-500 tabular-nums hover:text-ink-900">{{ PhoneNumber::format($appointment->customer->phone) }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-500">Mənbə</dt>
                        <dd class="mt-1 font-medium">
                            {{ $appointment->source->label() }}@if ($appointment->creator) · {{ $appointment->creator->name }}@endif
                        </dd>
                    </div>
                    @if ($status === S::Cancelled)
                        <div>
                            <dt class="text-sm text-ink-500">Ləğv səbəbi</dt>
                            <dd class="mt-1 font-medium">{{ $appointment->cancellation_reason ?: '—' }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            {{-- ── Əməliyyatlar ───────────────────────────────── --}}
            @if ($canUpdate && ! in_array($status, [S::Completed, S::Cancelled, S::NoShow], true))
                <aside class="card h-fit space-y-2 p-5">
                    <h2 class="mb-3 font-semibold">Əməliyyatlar</h2>

                    @if ($status->canTransitionTo(S::Confirmed))
                        <form method="POST" action="{{ route('panel.appointments.status', $appointment) }}">
                            @csrf @method('PATCH')
                            <button name="status" value="confirmed" class="btn-primary w-full"><i data-lucide="check" class="size-4"></i> Təsdiqlə</button>
                        </form>
                    @endif

                    @if ($status->canTransitionTo(S::Completed))
                        <form method="POST" action="{{ route('panel.appointments.status', $appointment) }}">
                            @csrf @method('PATCH')
                            <button name="status" value="completed" class="btn w-full bg-emerald-600 text-white hover:bg-emerald-700" @disabled(! $started)
                                    @if (! $started) title="Rezervasiyanın vaxtı hələ çatmayıb" @endif>
                                <i data-lucide="circle-check" class="size-4"></i> Tamamlandı
                            </button>
                        </form>
                        <form method="POST" action="{{ route('panel.appointments.status', $appointment) }}">
                            @csrf @method('PATCH')
                            <button name="status" value="no_show" class="btn-ghost w-full border border-ink-200" @disabled(! $started)>
                                <i data-lucide="user-x" class="size-4"></i> Müştəri gəlmədi
                            </button>
                        </form>
                        @unless ($started)
                            <p class="text-xs text-ink-500">«Tamamlandı» və «Gəlmədi» rezervasiyanın vaxtı çatanda aktiv olur.</p>
                        @endunless
                    @endif

                    @can('reschedule', $appointment)
                        <a href="{{ route('panel.appointments.edit', $appointment) }}" class="btn-ghost w-full border border-ink-200">
                            <i data-lucide="calendar-clock" class="size-4"></i> Başqa vaxta keçir
                        </a>
                    @endcan

                    <form method="POST" action="{{ route('panel.appointments.cancel', $appointment) }}" class="border-t border-ink-100 pt-3"
                          x-data="{ open: false }" onsubmit="return confirm('Rezervasiya ləğv edilsin?')">
                        @csrf
                        <button type="button" x-show="!open" @click="open = true" class="btn w-full text-red-700 hover:bg-red-50">Ləğv et</button>
                        <div x-show="open" x-cloak class="space-y-2">
                            <label for="reason" class="field-label">Ləğv səbəbi</label>
                            <input id="reason" name="reason" maxlength="255" class="field-input" placeholder="Müştəri zəng edib">
                            <button type="submit" class="btn w-full border border-red-200 text-red-700 hover:bg-red-50">Ləğvi təsdiqlə</button>
                        </div>
                    </form>
                </aside>
            @endif
        </div>

        {{-- ── Tarixçə (audit) ──────────────────────────────── --}}
        @if ($history->isNotEmpty())
            <section class="card mt-6">
                <h2 class="border-b border-ink-100 px-5 py-4 font-semibold">Tarixçə</h2>
                <ol class="divide-y divide-ink-100 text-sm">
                    @foreach ($history as $log)
                        <li class="flex flex-wrap items-baseline justify-between gap-2 px-5 py-3">
                            <span>
                                <span class="font-medium">{{ __('audit.'.$log->action) }}</span>
                                <span class="text-ink-500">· {{ $log->user?->name ?? 'Müştəri' }}</span>
                            </span>
                            <time class="text-ink-500 tabular-nums">{{ $log->created_at->setTimezone($tz)->format('d.m.Y H:i') }}</time>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </div>
@endsection
