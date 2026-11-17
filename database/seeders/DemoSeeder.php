<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Barber;
use App\Models\BusinessSetting;
use App\Models\User;
use App\Support\UniqueSlug;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * YALNIZ lokal inkişaf üçün demo data. Production-da işə düşmür (DatabaseSeeder-ə bax).
 *
 * Demo login-lər (parol hamısı üçün: "password"):
 *   manager@barbershop.test   — Menecer
 *   rauf@barbershop.test      — Bərbər
 *   elvin@barbershop.test     — Bərbər
 *   (Tural — login hesabı olmayan bərbər)
 *
 * Hər bərbərin öz xidmətləri və qiymətləri var (məs. Raufun saç kəsimi 20 ₼, Elvininki 15 ₼).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Demo əlaqə məlumatları (real deyil) — saytın tam görünüşünü yoxlamaq üçün
        BusinessSetting::query()->update([
            'address' => 'Nizami küçəsi 10, Bakı (demo ünvan)',
            'phone' => '+994 12 555 00 00',
        ]);

        User::create([
            'name' => 'Menecer',
            'email' => 'manager@barbershop.test',
            'password' => 'password',
            'role' => UserRole::Manager,
        ]);

        // [ad, açıqlama, [Rauf qiymət, Elvin qiymət, Tural qiymət | null = göstərmir], müddət]
        $catalog = [
            ['Saç kəsimi', 'Klassik və ya müasir kəsim, yuyulma və üslub daxil.', [20, 15, 15], 30],
            ['Saqqal düzəltmə', 'Saqqalın formalaşdırılması və konturun ülgüclə işlənməsi.', [12, 10, 10], 20],
            ['Saç + saqqal', 'Tam baxım: saç kəsimi və saqqal düzəltmə bir seansda.', [28, 22, 22], 50],
            ['Ülgüclə qırxım', 'İsti dəsmal ilə ənənəvi ülgüc qırxımı.', [15, null, 12], 30],
            ['Uşaq saç kəsimi', '12 yaşa qədər uşaqlar üçün.', [null, 10, 10], 25],
            ['Saç boyama', 'Ağ saçların örtülməsi və ya tonlama.', [null, 30, null], 60],
        ];

        $barbers = [
            ['Rauf', 'Baş bərbər', 'rauf@barbershop.test', 'Klassik kəsimlər və ülgüc qırxımı üzrə 12 illik təcrübə.', [1, 2, 3, 4, 5, 6]],
            ['Elvin', 'Bərbər', 'elvin@barbershop.test', 'Fade və müasir üslublar üzrə mütəxəssis.', [2, 3, 4, 5, 6, 7]],
            ['Tural', 'Bərbər', null, 'Saqqal dizaynı və uşaq kəsimləri.', [1, 2, 3, 4, 5]],
        ];

        foreach ($barbers as $i => [$name, $title, $email, $bio, $workDays]) {
            $user = $email ? User::create([
                'name' => $name,
                'email' => $email,
                'password' => 'password',
                'role' => UserRole::Barber,
            ]) : null;

            $barber = Barber::create([
                'user_id' => $user?->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'title' => $title,
                'bio' => $bio,
                'sort_order' => $i,
            ]);

            foreach ($catalog as $order => [$serviceName, $description, $prices, $duration]) {
                if ($prices[$i] === null) {
                    continue;
                }

                // HasMany::create barber_id-ni özü təyin edir (fillable-dan keçmir)
                $barber->services()->create([
                    'name' => $serviceName,
                    'slug' => UniqueSlug::for(Service::class, $serviceName.' '.$name),
                    'description' => $description,
                    'price' => $prices[$i],
                    'duration_minutes' => $duration,
                    'sort_order' => $order,
                ]);
            }

            foreach ($workDays as $weekday) {
                $barber->workingHours()->create(['weekday' => $weekday, 'start_time' => '10:00', 'end_time' => '20:00']);
                $barber->breaks()->create(['weekday' => $weekday, 'start_time' => '14:00', 'end_time' => '15:00', 'label' => 'Nahar']);
            }
        }
    }
}
