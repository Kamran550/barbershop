@extends('layouts.panel')

@section('title', 'Yeni xidmət')

@section('content')
    <div class="mx-auto max-w-5xl">
        <x-panel.page-header title="Yeni xidmət" :back="route('panel.services.index')" />
        <x-panel.flash />

        <form method="POST" action="{{ route('panel.services.store') }}" enctype="multipart/form-data">
            @csrf
            @include('panel.services._form')

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('panel.services.index') }}" class="btn-ghost">Ləğv et</a>
                <button type="submit" class="btn-primary">Yadda saxla</button>
            </div>
        </form>
    </div>
@endsection
