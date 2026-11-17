<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bərbərin public profili. Login hesabı (users) ayrıdır və optionaldır:
 * bərbər sistemə daxil olmadan da qrafikdə və saytda mövcud ola bilər.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barbers', function (Blueprint $table) {
            $table->id();
            // 1:0..1 — bir user ən çox bir bərbərə bağlıdır (unique)
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->string('title', 100)->nullable();       // məs. "Baş bərbər"
            $table->text('bio')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('telegram_chat_id', 64)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // Public sayt: "aktiv bərbərlər, sıra ilə"
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barbers');
    }
};
