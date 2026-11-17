@extends('layouts.panel')

@section('title', 'Profilim')

@section('content')
    <div class="mx-auto max-w-5xl">
        <x-panel.page-header title="Profilim" description="Saytda müştərilərə görünən məlumatlarınız." />
        <x-panel.flash />

        <form method="POST" action="{{ route('panel.profile.update') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-[1fr_18rem]">
            @csrf
            @method('PUT')

            <section class="card space-y-5 p-5 sm:p-6">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="name" class="field-label">Ad</label>
                        <input id="name" name="name" value="{{ old('name', $barber->name) }}" class="field-input" maxlength="100" required
                               @error('name') aria-invalid="true" @enderror>
                        @error('name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="title" class="field-label">Vəzifə</label>
                        <input id="title" name="title" value="{{ old('title', $barber->title) }}" class="field-input" maxlength="100" placeholder="Bərbər"
                               @error('title') aria-invalid="true" @enderror>
                        @error('title') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label for="bio" class="field-label">Haqqımda</label>
                    <textarea id="bio" name="bio" rows="4" class="field-input" maxlength="1000"
                              placeholder="Təcrübəniz, sevdiyiniz üslublar…" @error('bio') aria-invalid="true" @enderror>{{ old('bio', $barber->bio) }}</textarea>
                    @error('bio') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="phone" class="field-label">Telefon <span class="font-normal text-ink-400">(saytda göstərilmir)</span></label>
                        <input id="phone" name="phone" type="tel" value="{{ old('phone', $barber->phone) }}" class="field-input"
                               @error('phone') aria-invalid="true" @enderror>
                        @error('phone') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="telegram_chat_id" class="field-label">Telegram chat ID</label>
                        <input id="telegram_chat_id" name="telegram_chat_id" inputmode="numeric" value="{{ old('telegram_chat_id', $barber->telegram_chat_id) }}" class="field-input"
                               @error('telegram_chat_id') aria-invalid="true" @enderror>
                        <p class="mt-1.5 text-xs text-ink-500">Yeni rezervasiya bildirişləri üçün.</p>
                        @error('telegram_chat_id') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary">Yadda saxla</button>
                </div>
            </section>

            <div class="card h-fit space-y-3 p-5" x-data="{ preview: @js(\App\Services\ImageStorage::url($barber->photo_path)), removed: false }">
                <p class="field-label mb-0">Şəkil</p>
                <div class="aspect-square overflow-hidden rounded-lg bg-ink-100">
                    <template x-if="preview && !removed">
                        <img :src="preview" alt="" class="size-full object-cover">
                    </template>
                    <div x-show="!preview || removed" class="flex size-full items-center justify-center text-ink-400">
                        <i data-lucide="image-plus" class="size-8"></i>
                    </div>
                </div>
                <label class="btn-ghost w-full cursor-pointer border border-dashed border-ink-200">
                    <i data-lucide="image-plus" class="size-4"></i> Şəkil seç
                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only"
                           @change="const f = $event.target.files[0]; if (f) { preview = URL.createObjectURL(f); removed = false }">
                </label>
                <p class="text-xs text-ink-500">JPG, PNG və ya WEBP, maksimum 2 MB.</p>
                @error('photo') <p class="field-error">{{ $message }}</p> @enderror
                @if ($barber->photo_path)
                    <label class="flex items-center gap-2 text-sm text-ink-600">
                        <input type="checkbox" name="remove_photo" value="1" class="size-4 accent-ink-900" x-model="removed"> Şəkli sil
                    </label>
                @endif
            </div>
        </form>

        {{-- Şifrə --}}
        <form method="POST" action="{{ route('panel.profile.password') }}" class="card mt-6 max-w-xl space-y-5 p-5 sm:p-6">
            @csrf
            @method('PUT')
            <div>
                <h2 class="font-semibold">Şifrəni dəyiş</h2>
                <p class="mt-1 text-sm text-ink-500">Giriş e-poçtu: {{ $user->email }}. E-poçtu dəyişmək üçün menecerə müraciət edin.</p>
            </div>

            <div>
                <label for="current_password" class="field-label">Hazırkı şifrə</label>
                <input id="current_password" name="current_password" type="password" class="field-input" autocomplete="current-password" required
                       @error('current_password', 'password') aria-invalid="true" @enderror>
                @error('current_password', 'password') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="new_password" class="field-label">Yeni şifrə</label>
                    <input id="new_password" name="password" type="password" class="field-input" autocomplete="new-password" required
                           @error('password', 'password') aria-invalid="true" @enderror>
                    @error('password', 'password') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password_confirmation" class="field-label">Yeni şifrə (təkrar)</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="field-input" autocomplete="new-password" required>
                </div>
            </div>
            <button type="submit" class="btn-primary">Şifrəni dəyiş</button>
        </form>
    </div>
@endsection
