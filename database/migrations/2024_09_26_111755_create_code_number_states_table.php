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
        Schema::create('code_number_states', function (Blueprint $table) {
            $table->id();

            $table->string('seller_id'); // Сохраняем Telegram chat_id продавца
            $table->string('buyer_id')->nullable(); // Сохраняем Telegram chat_id покупателя

            $table->boolean('waiting_code')->default(true);
            $table->integer('request_count')->nullable();

            $table->string('number')->nullable();
            $table->string('provider')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('code_number_states');
    }
};
