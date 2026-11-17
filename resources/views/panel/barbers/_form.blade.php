@php
    $hasAccount = $barber->exists && $barber->user_id;
@endphp

<div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
    <div class="space-y-6">
        {{-- Profil --}}
        <section class="card space-y-5 p-5 sm:p-6">
            <h2 class="font-semibold">Profil</h2>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="field-label">Ad</label>
                    <input id="name" name="name" value="{{ old('name', $barber->name) }}" class="field-input" maxlength="100" required
                           @error('name') aria-invalid="true" @enderror>
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="title" class="field-label">Vəzifə <span class="font-normal text-ink-400">(istəyə bağlı)</span></label>
                    <input id="title" name="title" value="{{ old('title', $barber->title) }}" class="field-input" maxlength="100" placeholder="Baş bərbər"
                           @error('title') aria-invalid="true" @enderror>
                    @error('title') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="bio" class="field-label">Haqqında <span class="font-normal text-ink-400">(saytda göstərilir)</span></label>
                <textarea id="bio" name="bio" rows="3" class="field-input" maxlength="1000"
                          @error('bio') aria-invalid="true" @enderror>{{ old('bio', $barber->bio) }}</textarea>
                @error('bio') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-5 sm:grid-cols-3">
                <div>
                    <label for="phone" class="field-label">Telefon</label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone', $barber->phone) }}" class="field-input" placeholder="+994 50 123 45 67"
                           @error('phone') aria-invalid="true" @enderror>
                    @error('phone') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="telegram_chat_id" class="field-label">Telegram chat ID</label>
                    <input id="telegram_chat_id" name="telegram_chat_id" inputmode="numeric" value="{{ old('telegram_chat_id', $barber->telegram_chat_id) }}" class="field-input"
                           @error('telegram_chat_id') aria-invalid="true" @enderror>
                    @error('telegram_chat_id') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="sort_order" class="field-label">Sıra</label>
                    <input id="sort_order" name="sort_order" type="number" min="0" max="999" value="{{ old('sort_order', $barber->sort_order) }}" class="field-input"
                           @error('sort_order') aria-invalid="true" @enderror>
                    @error('sort_order') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <input id="is_active" name="is_active" type="checkbox" value="1" class="size-4 accent-ink-900" @checked(old('is_active', $barber->is_active))>
                <label for="is_active" class="text-sm">Aktiv — saytda görünür və rezervasiya qəbul edir. Deaktiv edilsə, login hesabı da bağlanır.</label>
            </div>
        </section>

        {{-- Xidmətlər: bərbərə məxsusdur, burada yalnız baxış --}}
        @if ($barber->exists)
            <section class="card p-5 sm:p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-semibold">Xidmətləri</h2>
                    <a href="{{ route('panel.services.create', ['barber' => $barber->id]) }}" class="text-sm font-medium text-brass-700 hover:text-brass-600">+ Xidmət əlavə et</a>
                </div>
                <p class="mt-1 text-sm text-ink-500">Bərbər öz xidmətlərini və qiymətlərini öz hesabından idarə edir.</p>

                @forelse ($barber->services as $service)
                    <div class="mt-3 flex items-center justify-between gap-3 rounded-lg border border-ink-100 px-3 py-2.5 text-sm">
                        <a href="{{ route('panel.services.edit', $service) }}" class="font-medium hover:underline">{{ $service->name }}</a>
                        <span class="text-ink-500">{{ $service->duration_minutes }} dəq · {{ \App\Support\Money::format($service->price) }}{{ $service->is_active ? '' : ' · deaktiv' }}</span>
                    </div>
                @empty
                    <p class="mt-4 text-sm text-ink-500">Hələ xidmət yoxdur — bərbər saytda görünəcək, amma rezervasiya üçün seçilə bilməyəcək.</p>
                @endforelse
            </section>
        @endif

        {{-- Login hesabı --}}
        <section class="card space-y-5 p-5 sm:p-6">
            <div>
                <h2 class="font-semibold">Panelə giriş</h2>
                <p class="mt-1 text-sm text-ink-500">
                    @if ($hasAccount)
                        Bərbər bu hesabla daxil olub öz xidmətlərini, qrafikini və rezervasiyalarını idarə edir. Şifrəni dəyişmək istəmirsinizsə, boş saxlayın.
                    @else
                        İstəyə bağlıdır. E-poçt yazsanız, bərbər üçün panel hesabı yaradılacaq.
                    @endif
                </p>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="email" class="field-label">E-poçt</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $barber->user?->email) }}" class="field-input" autocomplete="off"
                           @error('email') aria-invalid="true" @enderror>
                    @error('email') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password" class="field-label">{{ $hasAccount ? 'Yeni şifrə' : 'Şifrə' }}</label>
                    <input id="password" name="password" type="password" class="field-input" autocomplete="new-password"
                           @error('password') aria-invalid="true" @enderror>
                    <p class="mt-1.5 text-xs text-ink-500">Ən azı 8 simvol, hərf və rəqəm.</p>
                    @error('password') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>
    </div>

    {{-- Şəkil --}}
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
        <p class="text-xs text-ink-500">JPG, PNG və ya WEBP, maksimum 2 MB. Kvadrat portret ən yaxşı görünür.</p>
        @error('photo') <p class="field-error">{{ $message }}</p> @enderror

        @if ($barber->photo_path)
            <label class="flex items-center gap-2 text-sm text-ink-600">
                <input type="checkbox" name="remove_photo" value="1" class="size-4 accent-ink-900" x-model="removed">
                Şəkli sil
            </label>
        @endif
    </div>
</div>
