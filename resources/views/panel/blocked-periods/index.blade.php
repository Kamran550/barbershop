@extends('layouts.panel')

@section('title', 'Bloklanmış vaxtlar')

@php $tz = \App\Models\BusinessSetting::timezone(); @endphp

@section('content')
    <div class="mx-auto max-w-5xl">
        <x-panel.page-header title="Bloklanmış vaxtlar"
                             :description="$isManager
                                ? 'Birdəfəlik bağlı intervallar: təmir, tədbir, bərbərin şəxsi işi. Bu vaxtlarda onlayn rezervasiya mümkün olmur.'
                                : 'Şəxsi işiniz üçün vaxtı bağlayın — bu intervalda müştərilər sizə yazıla bilməyəcək.'" />

        <x-panel.flash />

        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
            <section class="card h-fit">
                <h2 class="border-b border-ink-100 px-5 py-4 font-semibold">Qarşıdakı bloklar</h2>
                @forelse ($blocks as $block)
                    @php
                        $start = $block->starts_at->setTimezone($tz);
                        $end = $block->ends_at->setTimezone($tz);
                    @endphp
                    <div class="flex items-center gap-3 border-b border-ink-100 px-5 py-3 last:border-0">
                        <span @class([
                            'flex size-9 shrink-0 items-center justify-center rounded-lg',
                            'bg-red-50 text-red-600' => ! $block->barber_id,
                            'bg-ink-100 text-ink-500' => $block->barber_id,
                        ])>
                            <i data-lucide="ban" class="size-4"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium">{{ $block->barber?->name ?? 'Bütün berberxana' }}</p>
                            <p class="text-sm text-ink-500">
                                {{ $start->format('d.m.Y H:i') }} — {{ $start->isSameDay($end) ? $end->format('H:i') : $end->format('d.m.Y H:i') }}
                                @if ($block->reason) · {{ $block->reason }} @endif
                            </p>
                        </div>
                        @can('delete', $block)
                            <form method="POST" action="{{ route('panel.blocked-periods.destroy', $block) }}" onsubmit="return confirm('Blok silinsin?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-ghost px-2" aria-label="Bloku sil"><i data-lucide="trash-2" class="size-4"></i></button>
                            </form>
                        @endcan
                    </div>
                @empty
                    <p class="px-5 py-6 text-sm text-ink-500">Qarşıda bloklanmış vaxt yoxdur.</p>
                @endforelse
            </section>

            <form method="POST" action="{{ route('panel.blocked-periods.store') }}" class="card h-fit space-y-4 p-5">
                @csrf
                <h2 class="font-semibold">Vaxtı blokla</h2>

                @if ($isManager)
                    <div>
                        <label for="barber_id" class="field-label">Kim üçün</label>
                        <select id="barber_id" name="barber_id" class="field-input">
                            <option value="">Bütün berberxana</option>
                            @foreach ($barbers as $barber)
                                <option value="{{ $barber->id }}" @selected(old('barber_id') == $barber->id)>{{ $barber->name }}</option>
                            @endforeach
                        </select>
                        @error('barber_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label for="starts_at" class="field-label">Başlama</label>
                    <input id="starts_at" type="datetime-local" step="300" name="starts_at" value="{{ old('starts_at') }}" class="field-input" required
                           @error('starts_at') aria-invalid="true" @enderror>
                    @error('starts_at') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="ends_at" class="field-label">Bitmə</label>
                    <input id="ends_at" type="datetime-local" step="300" name="ends_at" value="{{ old('ends_at') }}" class="field-input" required
                           @error('ends_at') aria-invalid="true" @enderror>
                    @error('ends_at') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="reason" class="field-label">Səbəb <span class="font-normal text-ink-400">(istəyə bağlı)</span></label>
                    <input id="reason" name="reason" value="{{ old('reason') }}" maxlength="255" class="field-input" placeholder="Təmir işləri">
                    @error('reason') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <p class="text-xs text-ink-500">Vaxtlar berberxananın saatı ilə ({{ $tz }}).</p>
                <button type="submit" class="btn-primary w-full">Blokla</button>
            </form>
        </div>
    </div>
@endsection
