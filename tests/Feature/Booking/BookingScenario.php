<?php

namespace Tests\Feature\Booking;

use App\Models\Barber;
use App\Models\BusinessSetting;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Database\Seeders\BusinessSettingSeeder;
use Illuminate\Support\Once;

/**
 * Booking testləri üçün sabit "dünya":
 *   - indi: 2030-05-06 Bazar ertəsi, Bakı vaxtı 10:00 (06:00 UTC)
 *   - parametrlər: slot 15 dəq, buffer 5, min notice 60, üfüq 30 gün, ləğv 2 saat əvvələ qədər, limit 3
 *   - Rauf və Elvin: B.e.–Şənbə 10:00–20:00, nahar 14:00–15:00; Bazar istirahət
 *   - Xidmətlər bərbərə məxsusdur, hər birinin öz "Saç kəsimi" xidməti var (30 dəq):
 *       Rauf → $haircut       15 ₼   (slug: sac-kesimi)
 *       Elvin → $elvinHaircut 20 ₼   (eyni ad → "Fərq etməz" qrupu)
 */
trait BookingScenario
{
    protected Service $haircut;

    protected Service $elvinHaircut;

    protected Barber $rauf;

    protected Barber $elvin;

    protected const TZ = 'Asia/Baku';

    protected const TUESDAY = '2030-05-07';

    protected function setUpScenario(): void
    {
        $this->seed(BusinessSettingSeeder::class);
        $this->travelTo(CarbonImmutable::parse('2030-05-06 06:00:00', 'UTC'));

        $this->rauf = $this->makeBarber('Rauf');
        $this->elvin = $this->makeBarber('Elvin');

        $this->haircut = Service::factory()->for($this->rauf)
            ->create(['name' => 'Saç kəsimi', 'slug' => 'sac-kesimi', 'price' => 15, 'duration_minutes' => 30]);
        $this->elvinHaircut = Service::factory()->for($this->elvin)
            ->create(['name' => 'Saç kəsimi', 'slug' => 'sac-kesimi-elvin', 'price' => 20, 'duration_minutes' => 30]);
    }

    /** Bərbərin "Saç kəsimi" xidməti (hər bərbərin özününkü). */
    protected function haircutOf(Barber $barber): Service
    {
        return $barber->is($this->elvin) ? $this->elvinHaircut : $this->haircut;
    }

    protected function makeBarber(string $name): Barber
    {
        $barber = Barber::factory()->create(['name' => $name]);

        foreach (range(1, 6) as $weekday) {
            $barber->workingHours()->create(['weekday' => $weekday, 'start_time' => '10:00', 'end_time' => '20:00']);
            $barber->breaks()->create(['weekday' => $weekday, 'start_time' => '14:00', 'end_time' => '15:00']);
        }

        return $barber;
    }

    /** Bakı vaxtı → CarbonImmutable */
    protected function baku(string $datetime): CarbonImmutable
    {
        return CarbonImmutable::parse($datetime, self::TZ);
    }

    /** @param list<CarbonImmutable> $slots  → ['10:00', '10:15', …] Bakı vaxtı ilə */
    protected function times(array $slots): array
    {
        return array_map(fn (CarbonImmutable $s) => $s->setTimezone(self::TZ)->format('H:i'), $slots);
    }

    protected function updateSettings(array $values): void
    {
        BusinessSetting::query()->update($values);
        Once::flush(); // BusinessSetting::current() request ərzində memoize olunur
    }
}
