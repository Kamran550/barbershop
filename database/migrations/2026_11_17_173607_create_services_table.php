<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            // Xidmət bərbərə məxsusdur: hər bərbər öz xidmətini, qiymətini və müddətini özü təyin edir.
            // Bərbər silinərsə (yalnız rezervasiyası yoxdursa mümkündür) xidmətləri də silinir.
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            // DECIMAL dəqiqdir; FLOAT pul üçün yuvarlaqlaşdırma xətası verir
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // "Bu bərbərin aktiv xidmətləri, sıra ilə" və "eyni adlı xidmətlər" (Fərq etməz seçimi)
            $table->index(['barber_id', 'is_active', 'sort_order']);
            $table->index('name');
        });

        // Data integrity DB səviyyəsində: tətbiqdə səhv olsa belə, yanlış dəyər yazıla bilməz
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_price_check CHECK (price >= 0)');
        DB::statement('ALTER TABLE services ADD CONSTRAINT services_duration_check CHECK (duration_minutes BETWEEN 5 AND 480)');
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
