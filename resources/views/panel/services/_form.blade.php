{{-- Yaratma və redaktə üçün ortaq forma. $service — yeni (exists=false) və ya mövcud model. --}}
<div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
    <div class="card space-y-5 p-5 sm:p-6">
        @if ($barbers->isNotEmpty())
            {{-- Yalnız menecer yeni xidmət yaradanda: kimin xidmətidir --}}
            <div>
                <label for="barber_id" class="field-label">Bərbər</label>
                <select id="barber_id" name="barber_id" class="field-input" required @error('barber_id') aria-invalid="true" @enderror>
                    <option value="">Seçin…</option>
                    @foreach ($barbers as $b)
                        <option value="{{ $b->id }}" @selected(old('barber_id', request('barber')) == $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
                @error('barber_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        @elseif ($service->exists && auth()->user()->isManager())
            <p class="text-sm text-ink-500">Bərbər: <span class="font-medium text-ink-900">{{ $service->barber->name }}</span></p>
        @endif

        <div>
            <label for="name" class="field-label">Xidmətin adı</label>
            <input id="name" name="name" value="{{ old('name', $service->name) }}" class="field-input" maxlength="100" required
                   @error('name') aria-invalid="true" @enderror>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
            <p class="mt-1.5 text-xs text-ink-500">
                Ümumi xidmətlər üçün digər ustalarla eyni addan istifadə edin (məs. «Saç kəsimi») —
                müştəri «Fərq etməz» seçəndə eyni adlı xidmətlər birlikdə göstərilir.
            </p>
        </div>

        <div>
            <label for="description" class="field-label">Açıqlama <span class="font-normal text-ink-400">(istəyə bağlı)</span></label>
            <textarea id="description" name="description" rows="3" class="field-input" maxlength="1000"
                      @error('description') aria-invalid="true" @enderror>{{ old('description', $service->description) }}</textarea>
            @error('description') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-3">
            <div>
                <label for="price" class="field-label">Qiymət (₼)</label>
                <input id="price" name="price" type="number" step="0.01" min="0" max="9999.99" inputmode="decimal"
                       value="{{ old('price', $service->price) }}" class="field-input" required
                       @error('price') aria-invalid="true" @enderror>
                @error('price') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="duration_minutes" class="field-label">Müddət (dəq)</label>
                <input id="duration_minutes" name="duration_minutes" type="number" step="5" min="5" max="480" inputmode="numeric"
                       value="{{ old('duration_minutes', $service->duration_minutes) }}" class="field-input" required
                       @error('duration_minutes') aria-invalid="true" @enderror>
                @error('duration_minutes') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="sort_order" class="field-label">Sıra</label>
                <input id="sort_order" name="sort_order" type="number" min="0" max="999" inputmode="numeric"
                       value="{{ old('sort_order', $service->sort_order) }}" class="field-input"
                       @error('sort_order') aria-invalid="true" @enderror>
                @error('sort_order') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="flex items-center gap-2.5">
            <input id="is_active" name="is_active" type="checkbox" value="1" class="size-4 accent-ink-900"
                   @checked(old('is_active', $service->is_active))>
            <label for="is_active" class="text-sm">Aktiv — saytda göstərilir və rezervasiya edilə bilər</label>
        </div>
    </div>

    {{-- Şəkil: seçiləndə dərhal önizləmə (Alpine) --}}
    <div class="card space-y-3 p-5" x-data="{ preview: @js(\App\Services\ImageStorage::url($service->image_path)), removed: false }">
        <p class="field-label mb-0">Şəkil</p>
        <div class="aspect-[4/3] overflow-hidden rounded-lg bg-ink-100">
            <template x-if="preview && !removed">
                <img :src="preview" alt="" class="size-full object-cover">
            </template>
            <div x-show="!preview || removed" class="flex size-full items-center justify-center text-ink-400">
                <i data-lucide="image-plus" class="size-8"></i>
            </div>
        </div>
        <label class="btn-ghost w-full cursor-pointer border border-dashed border-ink-200">
            <i data-lucide="image-plus" class="size-4"></i> Şəkil seç
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" class="sr-only"
                   @change="const f = $event.target.files[0]; if (f) { preview = URL.createObjectURL(f); removed = false }">
        </label>
        <p class="text-xs text-ink-500">JPG, PNG və ya WEBP, maksimum 2 MB.</p>
        @error('image') <p class="field-error">{{ $message }}</p> @enderror

        @if ($service->image_path)
            <label class="flex items-center gap-2 text-sm text-ink-600">
                <input type="checkbox" name="remove_image" value="1" class="size-4 accent-ink-900" x-model="removed">
                Şəkli sil
            </label>
        @endif
    </div>
</div>
