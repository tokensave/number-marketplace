<?php

use App\Models\Salesman;
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
        Schema::create('numbers', function (Blueprint $table) {
            $table->id();

            $table->string('number')->nullable()->comment('Номер продавца для продажи');
            $table->string('type_number')->nullable()->comment('Тип номера(whatsUp, telegram)');
            $table->string('status_number')->nullable()->comment('Статус номера');
            $table->string('buyer_uuid')->nullable()->comment('Uuid покупателя');

            $table->foreignId('salesman_id')
                ->constrained('salesmen')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('numbers');
    }
};
