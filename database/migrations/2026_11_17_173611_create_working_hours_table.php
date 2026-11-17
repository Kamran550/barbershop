<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bərbərin həftəlik qrafiki. Həftənin hər günü üçün ən çox bir sətir.
 * Sətir yoxdursa — həmin gün istirahət günüdür.
 * Saatlar "divar saatı"dır (berberxananın timezone-u ilə), UTC deyil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // ISO-8601: 1 = Bazar ertəsi … 7 = Bazar
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->unique(['barber_id', 'weekday']);
        });

        DB::statement('ALTER TABLE working_hours ADD CONSTRAINT working_hours_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE working_hours ADD CONSTRAINT working_hours_range_check CHECK (start_time < end_time)');
    }

    public function down(): void
    {
        Schema::dropIfExists('working_hours');
    }
};
