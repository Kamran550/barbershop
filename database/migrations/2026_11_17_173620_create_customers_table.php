<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Müştəri login olmur — telefon nömrəsi ilə tanınır.
 * Ziyarət sayı, xərclənən məbləğ, son ziyarət burada SAXLANMIR:
 * onlar appointments-dən hesablanır (normalizasiya — data bir yerdə yaşayır).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            // Normalizə olunmuş E.164 formatı: +994501234567
            $table->string('phone', 20)->unique();
            $table->timestamps();

            $table->index('name'); // admin paneldə ada görə axtarış
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
