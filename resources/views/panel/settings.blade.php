@extends('layouts.panel')

@section('title', 'Parametrlər')

@php
    use App\Support\Weekday;
    $field = fn (string $name) => old($name, $settings->{$name});
    $hours = $settings->opening_hours ?? [];
@endphp

@section('content')
    <div class="mx-auto max-w-4xl">
        <x-panel.page-header title="Parametrlər" description="Berberxana məlumatları, iş saatları, rezervasiya qaydaları və bildirişlər." />
        <x-panel.flash />

        <form method="POST" action="{{ route('panel.settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- ── Berberxana ──────────────────────────────── --}}
            <section class="card space-y-5 p-5 sm:p-6">
                <h2 class="font-semibold">Berberxana məlumatları</h2>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="name" class="field-label">Ad</label>
                        <input id="name" name="name" value="{{ $field('name') }}" class="field-input" required maxlength="100">
                        @error('name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="tagline" class="field-label">Şüar <span class="font-normal text-ink-400">(saytın başlığı)</span></label>
                        <input id="tagline" name="tagline" value="{{ $field('tagline') }}" class="field-input" maxlength="255">
                        @error('tagline') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label for="description" class="field-label">Qısa təsvir</label>
                    <textarea id="description" name="description" rows="2" maxlength="1000" class="field-input">{{ $field('description') }}</textarea>
                    @error('description') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="address" class="field-label">Ünvan</label>
                        <input id="address" name="address" value="{{ $field('address') }}" class="field-input" maxlength="255">
                        @error('address') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="phone" class="field-label">Telefon</label>
                        <input id="phone" name="phone" type="tel" value="{{ $field('phone') }}" class="field-input">
                        @error('phone') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="email" class="field-label">E-poçt</label>
                        <input id="email" name="email" type="email" value="{{ $field('email') }}" class="field-input">
                        @error('email') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="instagram_url" class="field-label">Instagram</label>
                        <input id="instagram_url" name="instagram_url" type="url" value="{{ $field('instagram_url') }}" placeholder="https://instagram.com/…" class="field-input">
                        @error('instagram_url') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            {{-- ── İş saatları (saytda göstərilir) ───────────── --}}
            <section class="card p-5 sm:p-6">
                <h2 class="font-semibold">İş saatları</h2>
                <p class="mt-1 text-sm text-ink-500">Saytda göstərilir. Boş vaxtlar isə hər bərbərin öz qrafikinə görə hesablanır.</p>
                <div class="mt-4 divide-y divide-ink-100">
                    @foreach (Weekday::LABELS as $wd => $label)
                        @php
                            $h = $hours[$wd] ?? null;
                            $open = (bool) old("opening_hours.$wd.open_day", $h !== null);
                        @endphp
                        <div class="flex flex-wrap items-center gap-3 py-3" x-data="{ open: @js($open) }">
                            <input type="hidden" name="opening_hours[{{ $wd }}][open_day]" :value="open ? 1 : 0">
                            <label class="flex w-44 items-center gap-2.5 text-sm font-medium">
                                <input type="checkbox" x-model="open" class="size-4 accent-ink-900"> {{ $label }}
                            </label>
                            <div x-show="open" class="flex items-center gap-2">
                                <input type="time" step="300" name="opening_hours[{{ $wd }}][open]" value="{{ old("opening_hours.$wd.open", $h['open'] ?? '10:00') }}"
                                       class="field-input w-36" :disabled="!open" aria-label="{{ $label }}: açılış">
                                <span class="text-ink-400">—</span>
                                <input type="time" step="300" name="opening_hours[{{ $wd }}][close]" value="{{ old("opening_hours.$wd.close", $h['close'] ?? '20:00') }}"
                                       class="field-input w-36" :disabled="!open" aria-label="{{ $label }}: bağlanış">
                            </div>
                            <span x-show="!open" class="text-sm text-ink-400">Bağlıdır</span>
                            @error("opening_hours.$wd.close") <p class="field-error w-full">{{ $message }}</p> @enderror
                            @error("opening_hours.$wd.open") <p class="field-error w-full">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- ── Rezervasiya qaydaları ───────────────────── --}}
            <section class="card space-y-5 p-5 sm:p-6">
                <h2 class="font-semibold">Rezervasiya qaydaları</h2>
                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <label for="slot_interval_minutes" class="field-label">Slot addımı</label>
                        <select id="slot_interval_minutes" name="slot_interval_minutes" class="field-input">
                            @foreach ([5, 10, 15, 20, 30, 60] as $m)
                                <option value="{{ $m }}" @selected($field('slot_interval_minutes') == $m)>{{ $m }} dəq</option>
                            @endforeach
                        </select>
                        <p class="mt-1.5 text-xs text-ink-500">Saatlar hansı addımla təklif olunsun.</p>
                    </div>
                    <div>
                        <label for="buffer_minutes" class="field-label">Buffer (dəq)</label>
                        <input id="buffer_minutes" name="buffer_minutes" type="number" min="0" max="60" value="{{ $field('buffer_minutes') }}" class="field-input">
                        <p class="mt-1.5 text-xs text-ink-500">Müştərilər arasında təmizlik vaxtı.</p>
                        @error('buffer_minutes') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="booking_horizon_days" class="field-label">Neçə gün qabağa</label>
                        <input id="booking_horizon_days" name="booking_horizon_days" type="number" min="1" max="180" value="{{ $field('booking_horizon_days') }}" class="field-input">
                        @error('booking_horizon_days') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="min_notice_minutes" class="field-label">Minimum xəbərdarlıq (dəq)</label>
                        <input id="min_notice_minutes" name="min_notice_minutes" type="number" min="0" max="2880" value="{{ $field('min_notice_minutes') }}" class="field-input">
                        <p class="mt-1.5 text-xs text-ink-500">Onlayn rezervasiya ən tezi neçə dəqiqə sonraya.</p>
                        @error('min_notice_minutes') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="cancel_deadline_minutes" class="field-label">Ləğv müddəti (dəq)</label>
                        <input id="cancel_deadline_minutes" name="cancel_deadline_minutes" type="number" min="0" max="2880" value="{{ $field('cancel_deadline_minutes') }}" class="field-input">
                        <p class="mt-1.5 text-xs text-ink-500">Müştəri başlanğıcdan bu qədər əvvələ qədər onlayn ləğv edə bilər.</p>
                        @error('cancel_deadline_minutes') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="max_active_bookings_per_phone" class="field-label">Bir nömrəyə limit</label>
                        <input id="max_active_bookings_per_phone" name="max_active_bookings_per_phone" type="number" min="1" max="20" value="{{ $field('max_active_bookings_per_phone') }}" class="field-input">
                        <p class="mt-1.5 text-xs text-ink-500">Eyni vaxtda aktiv onlayn rezervasiya sayı.</p>
                        @error('max_active_bookings_per_phone') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="timezone" class="field-label">Saat qurşağı</label>
                        <input id="timezone" name="timezone" value="{{ $field('timezone') }}" class="field-input" required>
                        @error('timezone') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex items-center gap-2.5 sm:pt-7">
                        <input id="auto_confirm" name="auto_confirm" type="checkbox" value="1" class="size-4 accent-ink-900" @checked($field('auto_confirm'))>
                        <label for="auto_confirm" class="text-sm">Onlayn rezervasiyalar avtomatik təsdiqlənsin</label>
                    </div>
                </div>
            </section>

            {{-- ── Telegram ────────────────────────────────── --}}
            <section class="card space-y-5 p-5 sm:p-6">
                <div>
                    <h2 class="font-semibold">Telegram bildirişləri</h2>
                    <p class="mt-1 text-sm text-ink-500">Yeni rezervasiya, ləğv və dəyişikliklər barədə menecerə və bərbərə mesaj.</p>
                </div>
                <div class="flex items-center gap-2.5">
                    <input id="telegram_enabled" name="telegram_enabled" type="checkbox" value="1" class="size-4 accent-ink-900" @checked($field('telegram_enabled'))>
                    <label for="telegram_enabled" class="text-sm">Bildirişlər aktivdir</label>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="telegram_bot_token" class="field-label">Bot token</label>
                        {{-- Token heç vaxt formaya geri yazılmır — yalnız "təyin edilib" göstərilir --}}
                        <input id="telegram_bot_token" name="telegram_bot_token" type="password" autocomplete="off" class="field-input"
                               placeholder="{{ $hasToken ? '•••••••• (təyin edilib — dəyişmək üçün yenisini yazın)' : '123456789:AAH…' }}">
                        @error('telegram_bot_token') <p class="field-error">{{ $message }}</p> @enderror
                        @if ($hasToken)
                            <label class="mt-2 flex items-center gap-2 text-sm text-ink-600">
                                <input type="checkbox" name="clear_telegram_token" value="1" class="size-4 accent-ink-900"> Tokeni sil
                            </label>
                        @endif
                    </div>
                    <div>
                        <label for="telegram_manager_chat_id" class="field-label">Menecerin chat ID-si</label>
                        <input id="telegram_manager_chat_id" name="telegram_manager_chat_id" inputmode="numeric" value="{{ $field('telegram_manager_chat_id') }}" class="field-input">
                        @error('telegram_manager_chat_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <div class="flex justify-end">
                <button type="submit" class="btn-primary px-6">Yadda saxla</button>
            </div>
        </form>
    </div>
@endsection
