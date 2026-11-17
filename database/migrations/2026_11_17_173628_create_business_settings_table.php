<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tək sətirli parametr cədvəli (bir berberxana = bir sətir).
 * Key-value əvəzinə tipli sütunlar: validation və default-lar sadə olur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();

            // Berberxana məlumatları
            $table->string('name', 100);
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('instagram_url')->nullable();
            // Saytda göstərilən ümumi iş saatları: {"1": {"open": "10:00", "close": "20:00"}, "7": null}
            // Yalnız göstərmək üçündür; boş vaxt bərbərin öz qrafiki ilə hesablanır.
            $table->json('opening_hours')->nullable();

            // Booking qaydaları
            $table->string('timezone', 64)->default('Asia/Baku');
            $table->unsignedSmallInteger('slot_interval_minutes')->default(15);
            $table->unsignedSmallInteger('buffer_minutes')->default(5);
            $table->unsignedSmallInteger('booking_horizon_days')->default(30);
            $table->unsignedSmallInteger('min_notice_minutes')->default(60);
            $table->unsignedSmallInteger('cancel_deadline_minutes')->default(120);
            $table->unsignedTinyInteger('max_active_bookings_per_phone')->default(3);
            $table->boolean('auto_confirm')->default(true);

            // Telegram
            $table->boolean('telegram_enabled')->default(false);
            $table->text('telegram_bot_token')->nullable(); // Laravel 'encrypted' cast ilə şifrələnir
            $table->string('telegram_manager_chat_id', 64)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
