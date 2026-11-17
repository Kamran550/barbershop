@extends('layouts.panel')

@section('title', $service->name)

@section('content')
    <div class="mx-auto max-w-5xl">
        <x-panel.page-header :title="$service->name" :back="route('panel.services.index')" />
        <x-panel.flash />

        <form method="POST" action="{{ route('panel.services.update', $service) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('panel.services._form')

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('panel.services.index') }}" class="btn-ghost">Ləğv et</a>
                <button type="submit" class="btn-primary">Yadda saxla</button>
            </div>
        </form>

        <form method="POST" action="{{ route('panel.services.destroy', $service) }}" class="mt-10 border-t border-ink-100 pt-6"
              onsubmit="return confirm({{ Js::from('«'.$service->name.'» silinsin? Bu əməliyyat geri qaytarıla bilməz.') }})">
            @csrf
            @method('DELETE')
            <p class="text-sm text-ink-500">Rezervasiya tarixçəsi olan xidmət silinmir — onu deaktiv edin.</p>
            <button type="submit" class="btn mt-3 border border-red-200 text-red-700 hover:bg-red-50">
                <i data-lucide="trash-2" class="size-4"></i> Xidməti sil
            </button>
        </form>
    </div>
@endsection
