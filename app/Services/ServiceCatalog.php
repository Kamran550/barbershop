<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Barber;
use App\Models\Service;
use App\Support\UniqueSlug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

/**
 * Xidmətlərin idarəsi: yaratmaq, redaktə, silmək + şəkil + audit.
 * Hər xidmət bir bərbərə məxsusdur; sahibi yaradılanda təyin olunur və sonra dəyişmir.
 * Controller yalnız validated datanı ötürür; qaydalar buradadır.
 */
class ServiceCatalog
{
    private const IMAGE_DIR = 'services';

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ImageStorage $images,
    ) {}

    /** @param Barber $owner xidmətin sahibi — controller kimin adına yaradıldığını (policy ilə) müəyyən edir */
    public function create(Barber $owner, array $data, ?UploadedFile $image = null): Service
    {
        $service = new Service($this->attributes($data));
        $service->barber_id = $owner->id; // fillable-dan yox, açıq təyin (mass assignment qoruması)
        $service->slug = UniqueSlug::for(Service::class, $service->name.' '.$owner->name);

        if ($image) {
            $service->image_path = $this->images->store($image, self::IMAGE_DIR);
        }

        $service->save();
        $this->audit->log('service.created', $service, new: $service->only(['barber_id', 'name', 'price', 'duration_minutes', 'is_active']));

        return $service;
    }

    public function update(Service $service, array $data, ?UploadedFile $image = null, bool $removeImage = false): Service
    {
        $service->fill($this->attributes($data));

        if ($service->isDirty('name')) {
            $service->slug = UniqueSlug::for(Service::class, $service->name.' '.$service->barber->name, $service->id);
        }

        if ($image) {
            $service->image_path = $this->images->replace($service->image_path, $image, self::IMAGE_DIR);
        } elseif ($removeImage && $service->image_path) {
            $this->images->delete($service->image_path);
            $service->image_path = null;
        }

        $service->save();
        // Qiymət dəyişikliyi də burada jurnala düşür. Keçmiş rezervasiyalar snapshot sayəsində təsirlənmir.
        $this->audit->logChanges('service.updated', $service);

        return $service;
    }

    /**
     * Rezervasiya tarixçəsi olan xidmət silinmir — hesabatlarda "Xidmət" filtri üçün lazımdır.
     * Belə xidmət deaktiv edilir (saytda görünmür, köhnə qeydlər qalır).
     */
    public function delete(Service $service): void
    {
        if ($service->appointments()->exists()) {
            throw new BusinessRuleException('Bu xidmət üzrə rezervasiyalar var. Silmək əvəzinə deaktiv edin.');
        }

        $this->audit->log('service.deleted', $service, old: $service->only(['barber_id', 'name', 'price', 'duration_minutes']));
        $service->delete();

        $this->images->delete($service->image_path);
    }

    private function attributes(array $data): array
    {
        return Arr::only($data, ['name', 'description', 'price', 'duration_minutes', 'is_active', 'sort_order']);
    }
}
