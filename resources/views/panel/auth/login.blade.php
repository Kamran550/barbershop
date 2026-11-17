@extends('layouts.panel-guest')

@section('title', 'Panelə giriş')

@section('content')
    <div class="mb-8 text-center">
        <div class="mx-auto mb-4 flex size-12 items-center justify-center rounded-xl bg-brass-500/15 text-brass-400 ring-1 ring-brass-500/30">
            <i data-lucide="scissors" class="size-6"></i>
        </div>
        <h1 class="text-xl font-semibold tracking-tight text-paper">{{ config('app.name') }}</h1>
        <p class="mt-1 text-sm text-ink-400">İdarəetmə panelinə giriş</p>
    </div>

    <div class="card p-6 shadow-2xl shadow-black/40 sm:p-7">
        <form method="POST" action="{{ route('panel.login.store') }}" class="space-y-5" novalidate>
            @csrf

            <div>
                <label for="email" class="field-label">E-poçt</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}"
                       class="field-input" autocomplete="username" autofocus required
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')
                    <p id="email-error" class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="field-label">Şifrə</label>
                <input id="password" name="password" type="password"
                       class="field-input" autocomplete="current-password" required
                       @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')
                    <p id="password-error" class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center gap-2.5">
                <input id="remember" type="checkbox" name="remember" value="1" class="size-4 rounded border-ink-300 accent-ink-900">
                <label for="remember" class="text-sm text-ink-600">Məni xatırla</label>
            </div>

            <button type="submit" class="btn-primary w-full py-3">
                Daxil ol
            </button>
        </form>
    </div>

    <p class="mt-6 text-center text-xs text-ink-500">
        Şifrənizi unutmusunuzsa, menecerlə əlaqə saxlayın.
    </p>
@endsection
