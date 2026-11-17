<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Göndərilən (və ya göndərilə bilməyən) bildirişlərin jurnalı.
 * Telegram xətası rezervasiyanı pozmur — sadəcə burada "failed" kimi qalır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20)->default('telegram');
            $table->string('event', 40);        // created | cancelled | rescheduled | status_changed
            $table->string('recipient', 64);    // chat_id
            $table->string('status', 20)->default('pending'); // pending | sent | failed
            $table->text('error')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
