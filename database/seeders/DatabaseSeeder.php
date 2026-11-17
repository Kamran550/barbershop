<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Hər mühitdə lazımdır
        $this->call(BusinessSettingSeeder::class);

        // Demo bərbərlər, xidmətlər, login-lər — yalnız lokal / test
        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoSeeder::class);
        }
    }
}
