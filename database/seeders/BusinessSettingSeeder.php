<?php

namespace Database\Seeders;

use App\Models\BusinessSetting;
use Illuminate\Database\Seeder;

/**
 * Parametrlərin yeganə sətrini yaradır. Production-da da işləyir (mövcuddursa toxunmur).
 */
class BusinessSettingSeeder extends Seeder
{
    public function run(): void
    {
        if (BusinessSetting::query()->exists()) {
            return;
        }

        $weekday = ['open' => '10:00', 'close' => '20:00'];

        BusinessSetting::create([
            'name' => 'Barbershop',
            'tagline' => 'Klassik üslub, müasir yanaşma',
            'timezone' => 'Asia/Baku',
            // 1 = Bazar ertəsi … 7 = Bazar; null = bağlıdır
            'opening_hours' => [1 => $weekday, 2 => $weekday, 3 => $weekday, 4 => $weekday, 5 => $weekday, 6 => $weekday, 7 => null],
        ]);
    }
}
