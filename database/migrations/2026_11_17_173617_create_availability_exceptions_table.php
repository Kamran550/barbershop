<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konkret tarix üçün həftəlik qrafikin əvəzlənməsi:
 *  - is_day_off = true  → həmin gün işləmir (məzuniyyət, bayram)
 *  - is_day_off = false → həmin gün xüsusi saatlarla işləyir
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_day_off')->default(true);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            // Bir bərbər üçün bir tarixdə yalnız bir istisna
            $table->unique(['barber_id', 'date']);
        });

        // İzin günü deyilsə, hər iki saat dolu olmalı və start < end
        DB::statement('ALTER TABLE availability_exceptions ADD CONSTRAINT availability_exceptions_hours_check CHECK (
            is_day_off = 1 OR (start_time IS NOT NULL AND end_time IS NOT NULL AND start_time < end_time)
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_exceptions');
    }
};
