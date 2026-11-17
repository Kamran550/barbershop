@extends('layouts.panel')

@php $isOwn = auth()->user()->barberId() === $barber->id; @endphp

@section('title', $isOwn ? 'Qrafikim' : "{$barber->name} — iş qrafiki")

@section('content')
    <div class="mx-auto max-w-5xl">
        <x-panel.page-header :title="$isOwn ? 'Qrafikim' : $barber->name.' — iş qrafiki'"
                             description="Həftəlik iş saatları və fasilələr hər həftə təkrarlanır. Məzuniyyət və ya fərqli saatlar üçün aşağıda tarix istisnası əlavə edin."
                             :back="$isOwn ? null : route('panel.barbers.index')">
            <x-slot:actions>
                @if ($isOwn)
                    <a href="{{ route('panel.blocked-periods.index') }}" class="btn-ghost border border-ink-200">
                        <i data-lucide="ban" class="size-4"></i> Vaxt blokla
                    </a>
                @else
                    <a href="{{ route('panel.barbers.edit', $barber) }}" class="btn-ghost border border-ink-200">
                        <i data-lucide="pencil" class="size-4"></i> Profil
                    </a>
                @endif
            </x-slot:actions>
        </x-panel.page-header>

        <x-panel.flash />

        {{-- ── Həftəlik qrafik ─────────────────────────────────────── --}}
        <form method="POST" action="{{ route('panel.barbers.schedule.update', $barber) }}"
              x-data="{ days: @js($days) }" class="card">
            @csrf
            @method('PUT')

            <div class="flex items-center justify-between border-b border-ink-100 px-5 py-4">
                <h2 class="font-semibold">Həftəlik qrafik</h2>
                <button type="submit" class="btn-primary">Yadda saxla</button>
            </div>

            <div class="divide-y divide-ink-100">
                <template x-for="day in days" :key="day.weekday">
                    <div class="grid gap-3 px-5 py-4 md:grid-cols-[11rem_1fr]">
                        {{-- Gün + iş günü / istirahət --}}
                        <div class="flex items-center gap-3 md:items-start md:pt-2">
                            <input type="hidden" :name="`days[${day.weekday}][enabled]`" :value="day.enabled ? 1 : 0">
                            <button type="button" role="switch" :aria-checked="day.enabled" @click="day.enabled = !day.enabled"
                                    :aria-label="`${day.label}: iş günü`"
                                    class="relative h-6 w-10 shrink-0 rounded-full transition"
                                    :class="day.enabled ? 'bg-ink-900' : 'bg-ink-200'">
                                <span class="absolute top-0.5 left-0.5 size-5 rounded-full bg-white shadow transition"
                                      :class="day.enabled && 'translate-x-4'"></span>
                            </button>
                            <span class="font-medium" x-text="day.label"></span>
                        </div>

                        <div>
                            <p x-show="!day.enabled" class="pt-2 text-sm text-ink-400">İstirahət günü</p>

                            <div x-show="day.enabled" class="space-y-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <input type="time" step="300" class="field-input w-36" x-model="day.start"
                                           :name="`days[${day.weekday}][start]`" :disabled="!day.enabled" :aria-label="`${day.label}: başlama`">
                                    <span class="text-ink-400">—</span>
                                    <input type="time" step="300" class="field-input w-36" x-model="day.end"
                                           :name="`days[${day.weekday}][end]`" :disabled="!day.enabled" :aria-label="`${day.label}: bitmə`">
                                </div>

                                <template x-for="(brk, i) in day.breaks" :key="i">
                                    <div class="flex flex-wrap items-center gap-2 pl-3 text-sm">
                                        <span class="w-14 text-ink-500">Fasilə</span>
                                        <input type="time" step="300" class="field-input w-36" x-model="brk.start"
                                               :name="`days[${day.weekday}][breaks][${i}][start]`" :disabled="!day.enabled">
                                        <span class="text-ink-400">—</span>
                                        <input type="time" step="300" class="field-input w-36" x-model="brk.end"
                                               :name="`days[${day.weekday}][breaks][${i}][end]`" :disabled="!day.enabled">
                                        <button type="button" class="btn-ghost px-2" @click="day.breaks.splice(i, 1)" aria-label="Fasiləni sil">
                                            <i data-lucide="x" class="size-4"></i>
                                        </button>
                                    </div>
                                </template>

                                <button type="button" class="inline-flex items-center gap-1 text-sm font-medium text-brass-700 hover:text-brass-600"
                                        @click="day.breaks.push({ start: '14:00', end: '15:00' }); $nextTick(() => renderIcons())">
                                    <i data-lucide="plus" class="size-4"></i> Fasilə əlavə et
                                </button>

                                <template x-for="err in day.errors">
                                    <p class="field-error" x-text="err"></p>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </form>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            {{-- ── Gələcək istisnalar ─────────────────────────────── --}}
            <section class="card">
                <h2 class="border-b border-ink-100 px-5 py-4 font-semibold">Tarix istisnaları</h2>
                @forelse ($exceptions as $exception)
                    <div class="flex items-center gap-3 border-b border-ink-100 px-5 py-3 last:border-0">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">{{ Str::ucfirst($exception->date->locale('az')->translatedFormat('j F, l')) }}</p>
                            <p class="text-sm text-ink-500">
                                {{ $exception->is_day_off ? 'İşləmir' : substr($exception->start_time, 0, 5).'–'.substr($exception->end_time, 0, 5) }}
                                @if ($exception->reason) · {{ $exception->reason }} @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('panel.barbers.exceptions.destroy', [$barber, $exception]) }}"
                              onsubmit="return confirm('İstisna silinsin?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-ghost px-2" aria-label="İstisnanı sil"><i data-lucide="trash-2" class="size-4"></i></button>
                        </form>
                    </div>
                @empty
                    <p class="px-5 py-6 text-sm text-ink-500">Gələcək üçün istisna yoxdur.</p>
                @endforelse
            </section>

            {{-- ── İstisna əlavə et ──────────────────────────────── --}}
            <form method="POST" action="{{ route('panel.barbers.exceptions.store', $barber) }}" class="card space-y-4 p-5"
                  x-data="{ dayOff: @js((bool) old('is_day_off', true)) }">
                @csrf
                <h2 class="font-semibold">İstisna əlavə et</h2>
                <p class="-mt-2 text-sm text-ink-500">Məzuniyyət, xəstəlik və ya həmin gün fərqli saatlarla işləmək. Eyni tarixdə istisna varsa, əvəz olunur.</p>

                <div>
                    <label for="ex-date" class="field-label">Tarix</label>
                    <input id="ex-date" type="date" name="date" min="{{ $today }}" value="{{ old('date') }}" class="field-input" required
                           @error('date') aria-invalid="true" @enderror>
                    @error('date') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <fieldset class="flex gap-4 text-sm">
                    <legend class="sr-only">İstisna növü</legend>
                    <label class="flex items-center gap-2"><input type="radio" name="is_day_off" x-model.boolean="dayOff" :value="true" class="accent-ink-900"> İşləmir</label>
                    <label class="flex items-center gap-2"><input type="radio" name="is_day_off" x-model.boolean="dayOff" :value="false" class="accent-ink-900"> Xüsusi saatlar</label>
                </fieldset>

                <div x-show="!dayOff" x-cloak class="flex flex-wrap items-start gap-2">
                    <div>
                        <label for="ex-start" class="sr-only">Başlama</label>
                        <input id="ex-start" type="time" step="300" name="start" value="{{ old('start', '10:00') }}" class="field-input w-36" :disabled="dayOff">
                    </div>
                    <span class="pt-2.5 text-ink-400">—</span>
                    <div>
                        <label for="ex-end" class="sr-only">Bitmə</label>
                        <input id="ex-end" type="time" step="300" name="end" value="{{ old('end', '16:00') }}" class="field-input w-36" :disabled="dayOff">
                    </div>
                </div>
                @error('start') <p class="field-error">{{ $message }}</p> @enderror
                @error('end') <p class="field-error">{{ $message }}</p> @enderror

                <div>
                    <label for="ex-reason" class="field-label">Səbəb <span class="font-normal text-ink-400">(istəyə bağlı)</span></label>
                    <input id="ex-reason" name="reason" value="{{ old('reason') }}" maxlength="255" class="field-input" placeholder="Məzuniyyət">
                    @error('reason') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="btn-primary w-full">Əlavə et</button>
            </form>
        </div>
    </div>
@endsection
