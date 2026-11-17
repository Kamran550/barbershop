@extends('layouts.panel')

@section('title', 'Yeni rezervasiya')

@php
    use App\Support\Money;
    // Alpine üçün: hər bərbərin öz xidmətləri
    $barberData = $barbers->map(fn ($b) => [
        'id' => $b->id,
        'name' => $b->name,
        'services' => $b->services->map(fn ($s) => [
            'id' => $s->id,
            'label' => "{$s->name} · {$s->duration_minutes} dəq · ".Money::format($s->price),
        ])->values(),
    ])->values();
@endphp

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-panel.page-header title="Yeni rezervasiya"
                             description="Telefonla zəng edən və ya qapıdan gələn müştəri üçün. Minimum xəbərdarlıq müddəti tətbiq olunmur, amma iş saatları, fasilələr və digər rezervasiyalar yoxlanılır."
                             :back="route('panel.appointments.index')" />
        <x-panel.flash />

        <form method="POST" action="{{ route('panel.appointments.store') }}" class="card space-y-5 p-5 sm:p-6"
              x-data="{
                  barbers: @js($barberData),
                  barberId: @js((int) old('barber_id', $preset['barber_id'] ?? 0) ?: null),
                  serviceId: @js((int) old('service_id') ?: null),
                  get services() { return this.barbers.find(b => b.id === this.barberId)?.services ?? [] },
              }">
            @csrf

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="barber_id" class="field-label">Bərbər</label>
                    <select id="barber_id" name="barber_id" class="field-input" x-model.number="barberId" @change="serviceId = null" required
                            @error('barber_id') aria-invalid="true" @enderror>
                        <option value="">Seçin…</option>
                        <template x-for="b in barbers" :key="b.id">
                            <option :value="b.id" x-text="b.name" :selected="b.id === barberId"></option>
                        </template>
                    </select>
                    @error('barber_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="service_id" class="field-label">Xidmət</label>
                    <select id="service_id" name="service_id" class="field-input" x-model.number="serviceId" :disabled="!barberId" required
                            @error('service_id') aria-invalid="true" @enderror>
                        <option value="">Seçin…</option>
                        <template x-for="s in services" :key="s.id">
                            <option :value="s.id" x-text="s.label" :selected="s.id === serviceId"></option>
                        </template>
                    </select>
                    @error('service_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="date" class="field-label">Tarix</label>
                    <input id="date" name="date" type="date" min="{{ $today }}" value="{{ old('date', $preset['date'] ?? $today) }}" class="field-input" required
                           @error('date') aria-invalid="true" @enderror>
                    @error('date') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="time" class="field-label">Saat</label>
                    <input id="time" name="time" type="time" step="300" value="{{ old('time', $preset['time'] ?? '') }}" class="field-input" required
                           @error('time') aria-invalid="true" @enderror>
                    @error('time') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid gap-5 border-t border-ink-100 pt-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="field-label">Müştərinin adı</label>
                    <input id="name" name="name" value="{{ old('name') }}" maxlength="100" class="field-input" required
                           @error('name') aria-invalid="true" @enderror>
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="phone" class="field-label">Telefon</label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="050 123 45 67" class="field-input" required
                           @error('phone') aria-invalid="true" @enderror>
                    <p class="mt-1.5 text-xs text-ink-500">Bu nömrə ilə müştəri artıq varsa, rezervasiya onun tarixçəsinə əlavə olunur.</p>
                    @error('phone') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('panel.appointments.index') }}" class="btn-ghost">Ləğv et</a>
                <button type="submit" class="btn-primary">Rezervasiya yarat</button>
            </div>
        </form>
    </div>
@endsection
