@extends('layouts.panel')

@section('title', 'Başqa vaxta keçir')

@section('content')
    <div class="mx-auto max-w-xl">
        <x-panel.page-header title="Başqa vaxta keçir"
                             :description="$appointment->customer->name.' · '.$appointment->service_name.' · indiki vaxt: '.$local->format('d.m.Y H:i')"
                             :back="route('panel.appointments.show', $appointment)" />
        <x-panel.flash />

        <form method="POST" action="{{ route('panel.appointments.update', $appointment) }}" class="card space-y-5 p-5 sm:p-6">
            @csrf
            @method('PUT')

            <div>
                <label for="barber_id" class="field-label">Bərbər</label>
                <select id="barber_id" name="barber_id" class="field-input" required>
                    @foreach ($barbers as $b)
                        <option value="{{ $b->id }}" @selected(old('barber_id', $appointment->barber_id) == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-ink-500">Yalnız «{{ $appointment->service_name }}» xidmətini göstərən bərbərlər. Qiymət dəyişmir.</p>
                @error('barber_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="date" class="field-label">Yeni tarix</label>
                    <input id="date" name="date" type="date" value="{{ old('date', $local->toDateString()) }}" class="field-input" required>
                    @error('date') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="time" class="field-label">Yeni saat</label>
                    <input id="time" name="time" type="time" step="300" value="{{ old('time', $local->format('H:i')) }}" class="field-input" required>
                    @error('time') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('panel.appointments.show', $appointment) }}" class="btn-ghost">Ləğv et</a>
                <button type="submit" class="btn-primary">Yadda saxla</button>
            </div>
        </form>
    </div>
@endsection
