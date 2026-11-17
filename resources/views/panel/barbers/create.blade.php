@extends('layouts.panel')

@section('title', 'Yeni bərbər')

@section('content')
    <div class="mx-auto max-w-5xl">
        <x-panel.page-header title="Yeni bərbər" :back="route('panel.barbers.index')" />
        <x-panel.flash />

        <form method="POST" action="{{ route('panel.barbers.store') }}" enctype="multipart/form-data">
            @csrf
            @include('panel.barbers._form')

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('panel.barbers.index') }}" class="btn-ghost">Ləğv et</a>
                <button type="submit" class="btn-primary">Yadda saxla və qrafikə keç</button>
            </div>
        </form>
    </div>
@endsection
