@extends('layouts.panel')

@section('title', $barber->name)

@section('content')
    <div class="mx-auto max-w-5xl">
        <x-panel.page-header :title="$barber->name" :back="route('panel.barbers.index')">
            <x-slot:actions>
                <a href="{{ route('panel.barbers.schedule', $barber) }}" class="btn-ghost border border-ink-200">
                    <i data-lucide="clock" class="size-4"></i> İş qrafiki
                </a>
            </x-slot:actions>
        </x-panel.page-header>
        <x-panel.flash />

        <form method="POST" action="{{ route('panel.barbers.update', $barber) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('panel.barbers._form')

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('panel.barbers.index') }}" class="btn-ghost">Ləğv et</a>
                <button type="submit" class="btn-primary">Yadda saxla</button>
            </div>
        </form>

        <form method="POST" action="{{ route('panel.barbers.destroy', $barber) }}" class="mt-10 border-t border-ink-100 pt-6"
              onsubmit="return confirm({{ Js::from($barber->name.' və onun login hesabı silinsin? Bu əməliyyat geri qaytarıla bilməz.') }})">
            @csrf
            @method('DELETE')
            <p class="text-sm text-ink-500">Rezervasiya tarixçəsi olan bərbər silinmir — onu deaktiv edin.</p>
            <button type="submit" class="btn mt-3 border border-red-200 text-red-700 hover:bg-red-50">
                <i data-lucide="trash-2" class="size-4"></i> Bərbəri sil
            </button>
        </form>
    </div>
@endsection
