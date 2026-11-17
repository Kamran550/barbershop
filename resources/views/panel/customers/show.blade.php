@extends('layouts.panel')

@section('title', $customer->name)

@php
    use App\Support\Money;
    use App\Support\PhoneNumber;
    $isManager = auth()->user()->isManager();
@endphp

@section('content')
    <div class="mx-auto max-w-6xl">
        <x-panel.page-header :title="$customer->name" :back="route('panel.customers.index')">
            <x-slot:actions>
                <a href="tel:{{ $customer->phone }}" class="btn-ghost border border-ink-200">
                    <i data-lucide="phone" class="size-4"></i> {{ PhoneNumber::format($customer->phone) }}
                </a>
            </x-slot:actions>
        </x-panel.page-header>

        <x-panel.flash />

        <div class="grid grid-cols-3 gap-3 sm:gap-4">
            <x-panel.stat label="Ziyarət" :value="$customer->visits_count" icon="check" />
            <x-panel.stat label="Xərclədiyi" :value="Money::format($customer->total_spent ?? 0)" icon="wallet" />
            <x-panel.stat label="Son ziyarət" icon="calendar"
                          :value="$customer->last_visit_at ? \Carbon\CarbonImmutable::parse($customer->last_visit_at, 'UTC')->setTimezone($tz)->format('d.m.Y') : '—'" />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_22rem]">
            {{-- ── Tarixçə ────────────────────────────────── --}}
            <section class="card h-fit">
                <h2 class="border-b border-ink-100 px-5 py-4 font-semibold">
                    {{ $isManager ? 'Rezervasiya tarixçəsi' : 'Sizdəki ziyarətlər' }}
                </h2>
                @forelse ($appointments as $a)
                    <a href="{{ route('panel.appointments.show', $a) }}" class="flex items-center gap-4 border-b border-ink-100 px-5 py-3 text-sm last:border-0 hover:bg-ink-100/40">
                        <span class="w-24 shrink-0 tabular-nums">
                            <span class="block font-medium">{{ $a->starts_at->setTimezone($tz)->format('d.m.Y') }}</span>
                            <span class="text-ink-500">{{ $a->starts_at->setTimezone($tz)->format('H:i') }}</span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ $a->service_name }}</span>
                            @if ($isManager)<span class="text-ink-500">{{ $a->barber->name }}</span>@endif
                        </span>
                        <span class="tabular-nums">{{ Money::format($a->service_price) }}</span>
                        <x-panel.appointment-status :status="$a->status" />
                    </a>
                @empty
                    <p class="px-5 py-8 text-center text-sm text-ink-500">Rezervasiya yoxdur.</p>
                @endforelse
                <div class="px-5"><x-panel.pagination :paginator="$appointments" /></div>
            </section>

            <div class="space-y-6">
                {{-- ── Private qeydlər ───────────────────── --}}
                <section class="card">
                    <div class="border-b border-ink-100 px-5 py-4">
                        <h2 class="font-semibold">Qeydlər</h2>
                        <p class="mt-0.5 text-xs text-ink-500">
                            <i data-lucide="lock" class="inline size-3"></i>
                            {{ $isManager ? 'Private: menecer və qeydin müəllifi görür.' : 'Private: yalnız siz və menecer görürsünüz.' }} Müştəri görmür.
                        </p>
                    </div>

                    @can('create', [App\Models\CustomerNote::class, $customer])
                        <form method="POST" action="{{ route('panel.customers.notes.store', $customer) }}" class="space-y-2 border-b border-ink-100 p-5">
                            @csrf
                            <label for="body" class="sr-only">Yeni qeyd</label>
                            <textarea id="body" name="body" rows="3" maxlength="2000" class="field-input" placeholder="Məs.: qısa fade sevir, saqqalı ülgüclə" required
                                      @error('body') aria-invalid="true" @enderror>{{ old('body') }}</textarea>
                            @error('body') <p class="field-error">{{ $message }}</p> @enderror
                            <button class="btn-primary w-full">Qeyd əlavə et</button>
                        </form>
                    @endcan

                    @forelse ($notes as $note)
                        <article class="border-b border-ink-100 px-5 py-3 text-sm last:border-0">
                            <p class="whitespace-pre-line">{{ $note->body }}</p>
                            <div class="mt-2 flex items-center justify-between text-xs text-ink-500">
                                <span>{{ $note->author?->name ?? 'Silinmiş istifadəçi' }} · {{ $note->created_at->setTimezone($tz)->format('d.m.Y H:i') }}</span>
                                @can('delete', $note)
                                    <form method="POST" action="{{ route('panel.customers.notes.destroy', [$customer, $note]) }}" onsubmit="return confirm('Qeyd silinsin?')">
                                        @csrf @method('DELETE')
                                        <button class="text-red-600 hover:underline">Sil</button>
                                    </form>
                                @endcan
                            </div>
                        </article>
                    @empty
                        <p class="px-5 py-6 text-sm text-ink-500">Hələ qeyd yoxdur.</p>
                    @endforelse
                </section>

                {{-- ── Məlumatlar (menecer) ───────────────── --}}
                @can('update', $customer)
                    <form method="POST" action="{{ route('panel.customers.update', $customer) }}" class="card space-y-4 p-5">
                        @csrf @method('PUT')
                        <h2 class="font-semibold">Məlumatlar</h2>
                        <div>
                            <label for="c-name" class="field-label">Ad</label>
                            <input id="c-name" name="name" value="{{ old('name', $customer->name) }}" class="field-input" required>
                            @error('name') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="c-phone" class="field-label">Telefon</label>
                            <input id="c-phone" name="phone" type="tel" value="{{ old('phone', PhoneNumber::format($customer->phone)) }}" class="field-input" required>
                            @error('phone') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <button class="btn-ghost w-full border border-ink-200">Yadda saxla</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
@endsection
