@extends('layouts.public')

@php
    use App\Support\Money;
    use App\Support\Weekday;
    use App\Services\ImageStorage;
@endphp

@section('content')
    {{-- ── HERO ───────────────────────────────────────────────── --}}
    <section class="relative overflow-hidden bg-ink-950 text-paper">
        <div aria-hidden="true" class="pointer-events-none absolute -top-48 -right-40 size-[40rem] rounded-full bg-brass-500/10 blur-3xl"></div>

        <div class="page-container relative grid items-center gap-12 py-16 sm:py-24 lg:grid-cols-[1fr_auto] lg:py-32">
            <div class="max-w-2xl">
                <p class="eyebrow">Onlayn rezervasiya</p>
                <h1 class="mt-5 font-display text-5xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-6xl lg:text-7xl">
                    {{ $settings->tagline ?: 'Klassik üslub, müasir yanaşma' }}
                </h1>
                <p class="mt-6 max-w-lg text-lg text-ink-300">
                    {{ $settings->description ?: 'Xidməti, ustanı və sizə uyğun saatı seçin — bir dəqiqədə, zəngsiz və növbəsiz.' }}
                </p>

                <div class="mt-10 flex flex-wrap gap-3">
                    <a href="{{ route('booking.create') }}" class="btn-brass">
                        Rezervasiya et <i data-lucide="chevron-right" class="size-4"></i>
                    </a>
                    <a href="#xidmetler" class="btn-outline-light">Xidmətlər və qiymətlər</a>
                </div>

                <dl class="mt-12 flex flex-wrap gap-x-10 gap-y-4 border-t border-paper/10 pt-8 text-sm">
                    <div>
                        <dt class="text-ink-500">Bu gün</dt>
                        <dd class="mt-1 font-medium">{{ $todayHours ? $todayHours['open'].' – '.$todayHours['close'] : 'Bağlıdır' }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-500">Ustalar</dt>
                        <dd class="mt-1 font-medium">{{ $barbers->count() }} bərbər</dd>
                    </div>
                    @if ($settings->address)
                        <div>
                            <dt class="text-ink-500">Ünvan</dt>
                            <dd class="mt-1 font-medium">{{ $settings->address }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Bərbər dirəyi — brendin vizual işarəsi --}}
            <div aria-hidden="true" class="hidden lg:flex lg:flex-col lg:items-center">
                <div class="h-6 w-20 rounded-t-full bg-gradient-to-b from-brass-300 to-brass-600"></div>
                <div class="h-80 w-14 overflow-hidden border-x-4 border-brass-600/60 bg-paper/10">
                    <div class="barber-pole size-full"></div>
                </div>
                <div class="h-6 w-20 rounded-b-full bg-gradient-to-t from-brass-300 to-brass-600"></div>
            </div>
        </div>
    </section>

    {{-- ── XİDMƏTLƏR: köhnə bərbərxana qiymət lövhəsi üslubunda ── --}}
    <section id="xidmetler" class="scroll-mt-16 py-20 sm:py-28">
        <div class="page-container">
            <div class="max-w-xl">
                <p class="eyebrow">Xidmətlər</p>
                <h2 class="mt-3 font-display text-4xl font-semibold tracking-tight sm:text-5xl">Qiymət siyahısı</h2>
                <p class="mt-4 text-ink-500">Hər ustanın öz qiyməti var — aralıq göstərilib. Qiymətə yuyulma və üslub daxildir.</p>
            </div>

            @if ($serviceGroups->isEmpty())
                <p class="mt-12 text-ink-500">Xidmətlər tezliklə əlavə olunacaq.</p>
            @else
                <ul class="mt-12 grid gap-x-16 lg:grid-cols-2">
                    @foreach ($serviceGroups as $group)
                        <li>
                            <a href="{{ route('booking.create', ['xidmet' => $group['key']]) }}"
                               class="group flex gap-4 border-b border-ink-200 py-6 transition hover:border-brass-500">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline gap-3">
                                        <h3 class="font-display text-xl font-semibold">{{ $group['name'] }}</h3>
                                        <span aria-hidden="true" class="mb-1.5 flex-1 border-b border-dotted border-ink-300"></span>
                                        <span class="font-display text-xl font-semibold whitespace-nowrap tabular-nums">{{ $group['price'] }}</span>
                                    </div>
                                    <div class="mt-1.5 flex items-start justify-between gap-4 text-sm text-ink-500">
                                        <p class="line-clamp-2">{{ $group['description'] }}</p>
                                        <span class="flex shrink-0 items-center gap-1">
                                            <i data-lucide="clock" class="size-3.5"></i> {{ $group['duration'] }}
                                        </span>
                                    </div>
                                    <span class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-brass-700 opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100">
                                        Seç və saat təyin et <i data-lucide="chevron-right" class="size-3.5"></i>
                                    </span>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- ── KOMANDA ───────────────────────────────────────────── --}}
    <section id="komanda" class="scroll-mt-16 bg-ink-100/60 py-20 sm:py-28">
        <div class="page-container">
            <div class="max-w-xl">
                <p class="eyebrow">Komanda</p>
                <h2 class="mt-3 font-display text-4xl font-semibold tracking-tight sm:text-5xl">Ustanızı seçin</h2>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($barbers as $barber)
                    <article class="group flex flex-col overflow-hidden rounded-[var(--radius-card)] bg-paper-raised ring-1 ring-ink-100">
                        <div class="relative aspect-[4/3] overflow-hidden bg-ink-900">
                            @if ($barber->photo_path)
                                <img src="{{ ImageStorage::url($barber->photo_path) }}" alt="{{ $barber->name }}" loading="lazy"
                                     class="size-full object-cover transition duration-500 group-hover:scale-105">
                            @else
                                {{-- Şəkil yoxdursa: monoqram --}}
                                <div class="flex size-full items-center justify-center">
                                    <span class="font-display text-8xl font-semibold text-brass-400/80 italic">{{ mb_substr($barber->name, 0, 1) }}</span>
                                </div>
                                <div aria-hidden="true" class="absolute inset-x-0 bottom-0 h-1.5 barber-pole"></div>
                            @endif
                        </div>
                        <div class="flex flex-1 flex-col p-6">
                            <h3 class="font-display text-2xl font-semibold">{{ $barber->name }}</h3>
                            <p class="text-sm font-medium text-brass-700">{{ $barber->title ?: 'Bərbər' }}</p>
                            @if ($barber->bio)
                                <p class="mt-3 text-sm text-ink-500">{{ $barber->bio }}</p>
                            @endif
                            {{-- Ustanın öz xidmətləri və qiymətləri --}}
                            <ul class="mt-4 flex-1 space-y-1.5 border-t border-ink-100 pt-4 text-sm">
                                @foreach ($barber->services->take(4) as $service)
                                    <li class="flex justify-between gap-3">
                                        <span class="truncate text-ink-600">{{ $service->name }}</span>
                                        <span class="shrink-0 font-medium tabular-nums">{{ Money::format($service->price) }}</span>
                                    </li>
                                @endforeach
                                @if ($barber->services->count() > 4)
                                    <li class="text-ink-400">+ daha {{ $barber->services->count() - 4 }} xidmət</li>
                                @endif
                            </ul>
                            <a href="{{ route('booking.create', ['barber' => $barber->slug]) }}"
                               class="mt-5 inline-flex items-center gap-1 text-sm font-semibold hover:text-brass-700">
                                {{ $barber->name }} ilə rezervasiya <i data-lucide="chevron-right" class="size-4"></i>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ── ƏLAQƏ VƏ İŞ SAATLARI ───────────────────────────────── --}}
    <section id="elaqe" class="scroll-mt-16 py-20 sm:py-28">
        <div class="page-container grid gap-12 lg:grid-cols-2">
            <div>
                <p class="eyebrow">Əlaqə</p>
                <h2 class="mt-3 font-display text-4xl font-semibold tracking-tight sm:text-5xl">Bizi tapın</h2>

                <ul class="mt-8 space-y-4">
                    @if ($settings->address)
                        <li class="flex gap-3"><i data-lucide="map-pin" class="mt-0.5 size-5 text-brass-600"></i>{{ $settings->address }}</li>
                    @endif
                    @if ($settings->phone)
                        <li class="flex gap-3"><i data-lucide="phone" class="mt-0.5 size-5 text-brass-600"></i>
                            <a href="tel:{{ preg_replace('/[^\d+]/', '', $settings->phone) }}" class="hover:underline">{{ $settings->phone }}</a>
                        </li>
                    @endif
                </ul>

                <a href="{{ route('booking.create') }}" class="btn-primary mt-10 rounded-full px-6 py-3">Onlayn rezervasiya</a>
            </div>

            <div class="card p-6 sm:p-8">
                <h3 class="font-display text-2xl font-semibold">İş saatları</h3>
                <dl class="mt-6 divide-y divide-ink-100">
                    @foreach (Weekday::LABELS as $weekday => $label)
                        @php $h = $settings->opening_hours[$weekday] ?? null; @endphp
                        <div @class(['flex justify-between py-3 text-sm', 'font-semibold' => now($settings->timezone)->isoWeekday() === $weekday])>
                            <dt>{{ $label }}</dt>
                            <dd class="tabular-nums {{ $h ? '' : 'text-ink-400' }}">{{ $h ? $h['open'].' – '.$h['close'] : 'Bağlıdır' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    </section>
@endsection
