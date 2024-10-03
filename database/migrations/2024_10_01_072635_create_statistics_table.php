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
        Schema::create('statistics', function (Blueprint $table) {
            $table->id();

            $table->string('uuid')->comment('Uuid продавец/покупатель');
            $table->string('type')->comment('Type продавец/покупатель');
            $table->string('provider_number')->nullable()->comment('Тип номера');
            $table->integer('count_active')->nullable()->comment('Количество купленных номеров');
            $table->integer('count_deactivate')->nullable()->comment('Количество слетевших номеров');
            $table->integer('count_pending')->nullable()->comment('Количество ожидающих очереди номеров');


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statistics');
    }
};
