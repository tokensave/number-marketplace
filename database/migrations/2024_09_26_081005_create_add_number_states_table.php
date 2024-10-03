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
        Schema::create('add_number_states', function (Blueprint $table) {
            $table->id();

            $table->string('seller_id')->nullable(); // Сохраняем Telegram chat_id продавца
            $table->boolean('waiting_for_number')->default(false);
            $table->string('provider')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('add_number_states');
    }
};
