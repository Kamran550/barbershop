<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Birdəfəlik bloklanmış vaxt intervalı (UTC).
 * barber_id = NULL → bütün berberxana bağlıdır (təmir, tədbir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->nullable()->constrained()->cascadeOnDelete();
            // DATETIME (TIMESTAMP yox): MySQL session timezone çevirməsi yoxdur və 2038 limiti yoxdur
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Sorğu: "bu bərbərin (və ya hamının) bu gün ilə kəsişən blokları"
            $table->index(['barber_id', 'starts_at']);
            $table->index('starts_at');
        });

        DB::statement('ALTER TABLE blocked_periods ADD CONSTRAINT blocked_periods_range_check CHECK (starts_at < ends_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_periods');
    }
};
