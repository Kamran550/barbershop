@extends('layouts.panel')

@section('title', 'Bərbərlər')

@section('content')
    <div class="mx-auto max-w-5xl">
        <x-panel.page-header title="Bərbərlər" description="Komanda, xidmətlər, login hesabları və iş qrafikləri.">
            <x-slot:actions>
                <a href="{{ route('panel.barbers.create') }}" class="btn-primary">
                    <i data-lucide="plus" class="size-4"></i> Yeni bərbər
                </a>
            </x-slot:actions>
        </x-panel.page-header>

        <x-panel.flash />

        @if ($barbers->isEmpty())
            <div class="card px-6 py-16 text-center">
                <i data-lucide="users" class="mx-auto size-8 text-ink-300"></i>
                <p class="mt-3 font-medium">Hələ bərbər yoxdur</p>
            </div>
        @else
            <ul class="card divide-y divide-ink-100">
                @foreach ($barbers as $barber)
                    <li class="flex flex-wrap items-center gap-4 px-4 py-3.5 sm:flex-nowrap sm:px-5">
                        @if ($barber->photo_path)
                            <img src="{{ \App\Services\ImageStorage::url($barber->photo_path) }}" alt=""
                                 class="size-12 shrink-0 rounded-full object-cover">
                        @else
                            <span class="flex size-12 shrink-0 items-center justify-center rounded-full bg-ink-100 font-semibold text-ink-500">
                                {{ mb_strtoupper(mb_substr($barber->name, 0, 1)) }}
                            </span>
                        @endif

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('panel.barbers.edit', $barber) }}" class="font-medium hover:underline">{{ $barber->name }}</a>
                                <x-panel.status-badge :active="$barber->is_active" />
                            </div>
                            <p class="mt-0.5 truncate text-sm text-ink-500">
                                {{ $barber->title ?: 'Bərbər' }} · {{ $barber->services_count }} xidmət ·
                                @if ($barber->user)
                                    {{ $barber->user->email }}
                                @else
                                    <span class="text-ink-400">login hesabı yoxdur</span>
                                @endif
                            </p>
                        </div>

                        <div class="flex w-full gap-2 sm:w-auto">
                            <a href="{{ route('panel.barbers.schedule', $barber) }}" class="btn-ghost flex-1 border border-ink-200 sm:flex-none">
                                <i data-lucide="clock" class="size-4"></i> Qrafik
                            </a>
                            <a href="{{ route('panel.barbers.edit', $barber) }}" class="btn-ghost flex-1 border border-ink-200 sm:flex-none">
                                <i data-lucide="pencil" class="size-4"></i> Redaktə
                            </a>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
