@extends('layouts.public')

@section('title', 'Rezervasiya · '.$settings->name)

@section('content')
    <div class="bg-ink-950 pb-28 text-paper sm:pb-16" x-data="bookingWizard(@js($config))">
        <div class="page-container max-w-3xl py-10 sm:py-14">

            {{-- ── Addım göstəricisi ───────────────────────────── --}}
            <ol class="grid grid-cols-4 gap-2 text-xs sm:text-sm" aria-label="Rezervasiya addımları">
                @foreach (['Usta', 'Xidmət', 'Vaxt', 'Məlumat'] as $i => $label)
                    @php $n = $i + 1; @endphp
                    <li>
                        <button type="button" @click="goTo({{ $n }})" :disabled="step <= {{ $n }}"
                                class="group w-full text-left disabled:cursor-default"
                                :aria-current="step === {{ $n }} ? 'step' : null">
                            <span class="block h-1 rounded-full transition"
                                  :class="step >= {{ $n }} ? 'bg-brass-400' : 'bg-paper/15'"></span>
                            <span class="mt-2 block font-medium transition"
                                  :class="step === {{ $n }} ? 'text-paper' : (step > {{ $n }} ? 'text-ink-300 group-hover:text-paper' : 'text-ink-500')">
                                {{ $n }}. {{ $label }}
                            </span>
                        </button>
                    </li>
                @endforeach
            </ol>

            @if (session('error'))
                <div role="alert" class="mt-8 flex items-start gap-3 rounded-xl border border-red-400/30 bg-red-500/10 px-4 py-3 text-sm text-red-100">
                    <i data-lucide="triangle-alert" class="mt-0.5 size-4 shrink-0"></i>
                    <p>{{ session('error') }}</p>
                </div>
            @endif

            {{-- ── 1. Usta ─────────────────────────────────────── --}}
            <section x-show="step === 1" class="mt-10">
                <h1 class="font-display text-3xl font-semibold sm:text-4xl">Usta seçin</h1>
                <p class="mt-2 text-ink-400">Hər ustanın öz xidmətləri və qiymətləri var.</p>

                <div class="mt-8 grid gap-3 sm:grid-cols-2">
                    <button type="button" @click="chooseBarber('any')"
                            class="flex items-center gap-4 rounded-2xl border px-5 py-4 text-left transition sm:col-span-2"
                            :class="barberId === 'any' ? 'border-brass-400 bg-brass-400/10' : 'border-paper/10 hover:border-paper/30 hover:bg-paper/5'">
                        <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-brass-400/15 text-brass-400">
                            <i data-lucide="users" class="size-5"></i>
                        </span>
                        <span>
                            <span class="block font-semibold">Fərq etməz</span>
                            <span class="block text-sm text-ink-400">İlk boş ustanı təyin edəcəyik — ən çox boş saat</span>
                        </span>
                    </button>

                    <p x-show="!barbers.length" class="text-ink-400 sm:col-span-2">Hazırda onlayn rezervasiya mümkün deyil.</p>

                    <template x-for="b in barbers" :key="b.id">
                        <button type="button" @click="chooseBarber(b.id)"
                                class="flex items-center gap-4 rounded-2xl border px-5 py-4 text-left transition"
                                :class="barberId === b.id ? 'border-brass-400 bg-brass-400/10' : 'border-paper/10 hover:border-paper/30 hover:bg-paper/5'">
                            <template x-if="b.photo">
                                <img :src="b.photo" alt="" class="size-12 shrink-0 rounded-full object-cover">
                            </template>
                            <template x-if="!b.photo">
                                <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-paper/10 font-display text-xl text-brass-300 italic" x-text="b.initial"></span>
                            </template>
                            <span>
                                <span class="block font-semibold" x-text="b.name"></span>
                                <span class="block text-sm text-ink-400" x-text="b.title || 'Bərbər'"></span>
                            </span>
                        </button>
                    </template>
                </div>
            </section>

            {{-- ── 2. Xidmət ───────────────────────────────────── --}}
            <section x-show="step === 2" x-cloak class="mt-10">
                <h1 class="font-display text-3xl font-semibold sm:text-4xl">Hansı xidmət?</h1>
                <p class="mt-2 text-ink-400" x-text="barberId === 'any' ? 'Qiymət seçilən ustaya görə aralıqdadır' : `${barber?.name} — qiymətlər`"></p>

                <div class="mt-8 grid gap-3">
                    {{-- Konkret usta: onun öz xidmətləri və qiymətləri --}}
                    <template x-if="barberId !== 'any'">
                        <div class="grid gap-3">
                            <template x-for="s in barberServices" :key="s.id">
                                <button type="button" @click="chooseService(s.id)"
                                        class="flex items-center gap-4 rounded-2xl border px-5 py-4 text-left transition"
                                        :class="serviceId === s.id ? 'border-brass-400 bg-brass-400/10' : 'border-paper/10 hover:border-paper/30 hover:bg-paper/5'">
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-display text-lg font-semibold" x-text="s.name"></span>
                                        <span class="mt-0.5 block truncate text-sm text-ink-400" x-text="s.description"></span>
                                    </span>
                                    <span class="shrink-0 text-right">
                                        <span class="block font-semibold tabular-nums" x-text="s.price"></span>
                                        <span class="block text-xs text-ink-400" x-text="`${s.duration} dəq`"></span>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </template>

                    {{-- Fərq etməz: eyni adlı xidmətlər qrup kimi, qiymət aralığı ilə --}}
                    <template x-if="barberId === 'any'">
                        <div class="grid gap-3">
                            <template x-for="g in groups" :key="g.key">
                                <button type="button" @click="chooseGroup(g)"
                                        class="flex items-center gap-4 rounded-2xl border px-5 py-4 text-left transition"
                                        :class="group?.key === g.key ? 'border-brass-400 bg-brass-400/10' : 'border-paper/10 hover:border-paper/30 hover:bg-paper/5'">
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-display text-lg font-semibold" x-text="g.name"></span>
                                        <span class="mt-0.5 block truncate text-sm text-ink-400" x-text="g.description"></span>
                                    </span>
                                    <span class="shrink-0 text-right">
                                        <span class="block font-semibold whitespace-nowrap tabular-nums" x-text="g.price"></span>
                                        <span class="block text-xs text-ink-400" x-text="g.duration"></span>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </section>

            {{-- ── 3. Tarix və saat ────────────────────────────── --}}
            <section x-show="step === 3" x-cloak class="mt-10">
                <h1 class="font-display text-3xl font-semibold sm:text-4xl">Vaxt seçin</h1>
                <p class="mt-2 text-ink-400"><span x-text="service?.name"></span> · <span x-text="barberLabel"></span></p>

                {{-- Üfüqi tarix zolağı --}}
                <div class="-mx-4 mt-8 overflow-x-auto px-4 pb-3 [scrollbar-color:var(--color-ink-700)_transparent] [scrollbar-width:thin]" role="listbox" aria-label="Tarix">
                    <div class="flex gap-2">
                        <template x-for="d in days" :key="d.value">
                            <button type="button" role="option" @click="pickDate(d.value)" :disabled="!d.open"
                                    :aria-selected="date === d.value"
                                    class="flex w-16 shrink-0 flex-col items-center rounded-2xl border py-3 transition disabled:cursor-not-allowed disabled:opacity-30"
                                    :class="date === d.value ? 'border-brass-400 bg-brass-400 text-ink-950' : 'border-paper/10 hover:border-paper/30'">
                                <span class="text-[11px] font-medium" :class="date === d.value ? 'text-ink-800' : 'text-ink-400'" x-text="d.weekday"></span>
                                <span class="mt-1 text-xl font-semibold tabular-nums" x-text="d.day"></span>
                                <span class="text-[11px]" :class="date === d.value ? 'text-ink-800' : 'text-ink-400'" x-text="d.month"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Saatlar --}}
                <div class="mt-8 min-h-48" aria-live="polite">
                    <div x-show="loading" class="flex items-center gap-2 text-ink-400">
                        <i data-lucide="loader-circle" class="size-4 animate-spin"></i> Boş saatlar yüklənir…
                    </div>

                    <p x-show="!loading && slotError" x-text="slotError" class="text-red-300"></p>

                    <div x-show="!loading && !slotError && date && !slots.length" class="rounded-2xl border border-dashed border-paper/15 px-5 py-8 text-center text-ink-400">
                        Bu gün üçün boş saat qalmayıb. Başqa tarix seçin.
                    </div>

                    <template x-if="!loading && slots.length">
                        <div class="space-y-6">
                            <template x-for="g in slotGroups" :key="g.label">
                                <div>
                                    <p class="mb-3 text-sm text-ink-400" x-text="g.label"></p>
                                    <div class="grid grid-cols-4 gap-2 sm:grid-cols-6">
                                        <template x-for="t in g.items" :key="t">
                                            <button type="button" @click="chooseTime(t)"
                                                    class="rounded-xl border py-2.5 text-sm font-semibold tabular-nums transition"
                                                    :class="time === t ? 'border-brass-400 bg-brass-400 text-ink-950' : 'border-paper/15 hover:border-brass-400 hover:text-brass-300'"
                                                    x-text="t"></button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </section>

            {{-- ── 4. Məlumat və təsdiq ────────────────────────── --}}
            <section x-show="step === 4" x-cloak class="mt-10">
                <h1 class="font-display text-3xl font-semibold sm:text-4xl">Son addım</h1>

                <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_17rem]">
                    <form method="POST" action="{{ route('booking.store') }}" class="space-y-5" @submit="submitting = true" novalidate>
                        @csrf
                        <input type="hidden" name="service_id" :value="serviceId">
                        <input type="hidden" name="barber_id" :value="barberId">
                        <input type="hidden" name="date" :value="date">
                        <input type="hidden" name="time" :value="time">

                        {{-- Honeypot: ekranda görünmür, insan doldurmur; bot doldurursa server rədd edir --}}
                        <div class="hidden" aria-hidden="true">
                            <label for="website">Vebsayt</label>
                            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                        </div>

                        <div>
                            <label for="name" class="mb-1.5 block text-sm font-medium text-ink-200">Adınız</label>
                            <input id="name" name="name" x-model="name" autocomplete="name" maxlength="100" required
                                   class="block w-full rounded-xl border border-paper/15 bg-paper/5 px-4 py-3 text-paper placeholder:text-ink-500 focus:border-brass-400 focus:ring-4 focus:ring-brass-400/20 focus:outline-none"
                                   @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                            @error('name') <p id="name-error" class="mt-1.5 text-sm text-red-300">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="phone" class="mb-1.5 block text-sm font-medium text-ink-200">Telefon</label>
                            <input id="phone" name="phone" x-model="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="050 123 45 67" maxlength="30" required
                                   class="block w-full rounded-xl border border-paper/15 bg-paper/5 px-4 py-3 text-paper placeholder:text-ink-500 focus:border-brass-400 focus:ring-4 focus:ring-brass-400/20 focus:outline-none"
                                   @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                            @error('phone') <p id="phone-error" class="mt-1.5 text-sm text-red-300">{{ $message }}</p> @enderror
                            <p class="mt-1.5 text-xs text-ink-500">Yalnız rezervasiya ilə bağlı əlaqə üçün. Qeydiyyat tələb olunmur.</p>
                        </div>

                        @foreach (['service_id', 'barber_id', 'date', 'time', 'website'] as $field)
                            @error($field) <p class="text-sm text-red-300">{{ $message }}</p> @enderror
                        @endforeach

                        <button type="submit" class="btn-brass w-full py-3.5 text-base" :disabled="submitting || !time">
                            <span x-show="!submitting">Rezervasiyanı təsdiqlə</span>
                            <span x-show="submitting" x-cloak class="flex items-center gap-2"><i data-lucide="loader-circle" class="size-4 animate-spin"></i> Göndərilir…</span>
                        </button>
                    </form>

                    {{-- Xülasə — bileti xatırladır --}}
                    <aside class="h-fit rounded-2xl bg-paper p-5 text-ink-900">
                        <p class="eyebrow text-brass-700">Rezervasiyanız</p>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-ink-500">Xidmət</dt><dd class="text-right font-medium" x-text="service?.name"></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-500">Usta</dt><dd class="text-right font-medium" x-text="barberLabel"></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-500">Tarix</dt><dd class="text-right font-medium" x-text="dateLabel"></dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-ink-500">Saat</dt><dd class="text-right font-medium tabular-nums" x-text="time"></dd></div>
                        </dl>
                        <div class="mt-4 flex justify-between border-t border-dashed border-ink-200 pt-4">
                            <span class="text-ink-500" x-text="durationLabel"></span>
                            <span class="font-display text-xl font-semibold" x-text="priceLabel"></span>
                        </div>
                    </aside>
                </div>
            </section>
        </div>

        {{-- Mobil: aşağıda sabit "geri" paneli --}}
        <div x-show="step > 1" x-cloak class="fixed inset-x-0 bottom-0 z-30 border-t border-paper/10 bg-ink-950/95 backdrop-blur sm:hidden">
            <div class="page-container flex items-center justify-between gap-3 py-3 text-sm">
                <button type="button" @click="goTo(step - 1)" class="flex items-center gap-1 text-ink-300">
                    <i data-lucide="chevron-left" class="size-4"></i> Geri
                </button>
                <span class="truncate text-right text-ink-400">
                    <span x-text="barberLabel"></span><template x-if="service"><span x-text="` · ${service.name}`"></span></template>
                </span>
            </div>
        </div>
    </div>
@endsection
