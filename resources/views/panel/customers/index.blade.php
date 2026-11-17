@extends('layouts.panel')

@section('title', 'Müştərilər')

@php
    use App\Support\Money;
    use App\Support\PhoneNumber;
    $tz = \App\Models\BusinessSetting::timezone();
@endphp

@section('content')
    <div class="mx-auto max-w-6xl">
        <x-panel.page-header title="Müştərilər"
                             :description="auth()->user()->isManager() ? 'Bütün müştərilər və onların ziyarət tarixçəsi.' : 'Sizə yazılmış müştərilər. Statistika yalnız sizdəki ziyarətlərə görədir.'" />

        <form method="GET" class="card mb-4 flex flex-col gap-3 p-4 sm:flex-row">
            <div class="relative flex-1">
                <label for="q" class="sr-only">Axtarış</label>
                <i data-lucide="search" class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-ink-400"></i>
                <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Ad və ya telefon" class="field-input pl-9">
            </div>
            <select name="sort" class="field-input sm:w-52" aria-label="Sıralama" onchange="this.form.submit()">
                <option value="recent" @selected($filters['sort'] === 'recent')>Son ziyarət</option>
                <option value="visits" @selected($filters['sort'] === 'visits')>Ziyarət sayı</option>
                <option value="spent" @selected($filters['sort'] === 'spent')>Xərclənən məbləğ</option>
                <option value="name" @selected($filters['sort'] === 'name')>Ad</option>
            </select>
            <button class="btn-primary">Axtar</button>
        </form>

        @if ($customers->isEmpty())
            <div class="card px-6 py-16 text-center text-sm text-ink-500">Müştəri tapılmadı.</div>
        @else
            <div class="card overflow-x-auto">
                <table class="w-full min-w-[40rem] text-sm">
                    <thead class="border-b border-ink-100 bg-ink-100/40 text-left text-xs text-ink-500">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Müştəri</th>
                            <th class="px-4 py-2.5 font-medium">Telefon</th>
                            <th class="px-4 py-2.5 text-right font-medium">Ziyarət</th>
                            <th class="px-4 py-2.5 text-right font-medium">Xərclədiyi</th>
                            <th class="px-4 py-2.5 font-medium">Son ziyarət</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        @foreach ($customers as $c)
                            <tr class="hover:bg-ink-100/40">
                                <td class="px-4 py-3"><a href="{{ route('panel.customers.show', $c) }}" class="font-medium hover:underline">{{ $c->name }}</a></td>
                                <td class="px-4 py-3 text-ink-600 tabular-nums">{{ PhoneNumber::format($c->phone) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $c->visits_count }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ Money::format($c->total_spent ?? 0) }}</td>
                                <td class="px-4 py-3 text-ink-600 tabular-nums">
                                    {{ $c->last_visit_at ? \Carbon\CarbonImmutable::parse($c->last_visit_at, 'UTC')->setTimezone($tz)->format('d.m.Y') : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-panel.pagination :paginator="$customers" />
        @endif
    </div>
@endsection
