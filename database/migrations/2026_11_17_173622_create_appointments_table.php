<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();

            // restrictOnDelete: rezervasiyası olan bərbər/müştəri silinə bilməz — tarixçə və gəlir itməsin.
            // Bərbər silinmir, deaktiv edilir.
            $table->foreignId('barber_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            // Xidmət silinsə belə rezervasiya qalır — snapshot sütunları kifayətdir
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();

            // SNAPSHOT: rezervasiya anındakı xidmət məlumatı. Sabah qiymət dəyişsə, keçmiş hesabat dəyişməz.
            $table->string('service_name', 100);
            $table->decimal('service_price', 10, 2);
            $table->unsignedSmallInteger('service_duration');

            // UTC. ends_at = starts_at + service_duration (buffer daxil deyil, o hesablama zamanı əlavə olunur)
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->string('status', 20)->default('pending');   // App\Enums\AppointmentStatus
            $table->string('source', 20)->default('online');    // App\Enums\AppointmentSource

            // Müştərinin linkindəki tokenin SHA-256 hash-i. Token özü DB-də saxlanmır.
            $table->char('token_hash', 64)->unique();

            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Availability və calendar: "bu bərbərin bu gün rezervasiyaları"
            $table->index(['barber_id', 'starts_at']);
            // Dashboard: "bugünkü / gələcək təsdiqlənmiş"
            $table->index(['status', 'starts_at']);
            // Müştəri tarixçəsi
            $table->index(['customer_id', 'starts_at']);
        });

        DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_range_check CHECK (starts_at < ends_at)');
        DB::statement('ALTER TABLE appointments ADD CONSTRAINT appointments_price_check CHECK (service_price >= 0)');
        DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_status_check CHECK (status IN ('pending','confirmed','completed','cancelled','no_show'))");
        DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_source_check CHECK (source IN ('online','manual'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
