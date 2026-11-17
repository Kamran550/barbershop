<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Həftəlik təkrarlanan fasilələr (məs. hər gün 14:00–15:00 nahar).
 * Bir gündə bir neçə fasilə ola bilər, ona görə unique yoxdur.
 * Model: App\Models\BreakPeriod ("break" PHP-də reserved sözdür).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('label', 100)->nullable(); // məs. "Nahar"
            $table->timestamps();

            $table->index(['barber_id', 'weekday']);
        });

        DB::statement('ALTER TABLE breaks ADD CONSTRAINT breaks_weekday_check CHECK (weekday BETWEEN 1 AND 7)');
        DB::statement('ALTER TABLE breaks ADD CONSTRAINT breaks_range_check CHECK (start_time < end_time)');
    }

    public function down(): void
    {
        Schema::dropIfExists('breaks');
    }
};
