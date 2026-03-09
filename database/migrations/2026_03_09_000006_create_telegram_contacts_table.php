<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('telegram_contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('phone_number')->unique();
            $table->string('telegram_chat_id');
            $table->string('telegram_user_id')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('telegram_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_contacts');
    }
};
