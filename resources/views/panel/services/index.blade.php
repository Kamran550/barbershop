@extends('layouts.panel')

@section('title', 'Xidmətlər')

@section('content')
    <div class="mx-auto max-w-5xl">
        <x-panel.page-header :title="$isManager ? 'Xidmətlər' : 'Xidmətlərim'"
                             :description="$isManager ? 'Hər bərbərin öz xidmətləri, qiymətləri və müddətləri.' : 'Göstərdiyiniz xidmətlər, qiymətləriniz və müddətlər. Müştərilər sizi yalnız bu xidmətlər üçün seçə bilər.'">
            <x-slot:actions>
                <a href="{{ route('panel.services.create') }}" class="btn-primary">
                    <i data-lucide="plus" class="size-4"></i> Yeni xidmət
                </a>
            </x-slot:actions>
        </x-panel.page-header>

        <x-panel.flash />

        @if ($services->isEmpty())
            <div class="card px-6 py-16 text-center">
                <i data-lucide="scissors" class="mx-auto size-8 text-ink-300"></i>
                <p class="mt-3 font-medium">Hələ xidmət yoxdur</p>
                <p class="mt-1 text-sm text-ink-500">İlk xidməti əlavə edin ki, müştərilər rezervasiya edə bilsin.</p>
            </div>
        @else
            <ul class="card divide-y divide-ink-100">
                @foreach ($services as $service)
                    <li class="flex items-center gap-4 px-4 py-3.5 sm:px-5">
                        @if ($service->image_path)
                            <img src="{{ \App\Services\ImageStorage::url($service->image_path) }}" alt=""
                                 class="size-12 shrink-0 rounded-lg object-cover">
                        @else
                            <span class="flex size-12 shrink-0 items-center justify-center rounded-lg bg-ink-100 text-ink-400">
                                <i data-lucide="scissors" class="size-5"></i>
                            </span>
                        @endif

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('panel.services.edit', $service) }}" class="truncate font-medium hover:underline">{{ $service->name }}</a>
                                <x-panel.status-badge :active="$service->is_active" />
                            </div>
                            <p class="mt-0.5 text-sm text-ink-500">
                                {{ $service->duration_minutes }} dəq
                                @if ($isManager) · {{ $service->barber->name }} @endif
                            </p>
                        </div>

                        <p class="shrink-0 font-semibold tabular-nums">{{ \App\Support\Money::format($service->price) }}</p>

                        <a href="{{ route('panel.services.edit', $service) }}" class="btn-ghost shrink-0 px-2.5" aria-label="«{{ $service->name }}» redaktə et">
                            <i data-lucide="pencil" class="size-4"></i>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
@endsection
