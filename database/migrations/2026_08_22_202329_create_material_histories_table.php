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
    Schema::create('material_histories', function (Blueprint $table) {
        $table->id();
        // Привязка к материалу
        $table->foreignId('material_id')->constrained()->cascadeOnDelete();

        // Тип операции: 'addition' (добавление/приход) или 'deduction' (списание/расход)
        $table->string('type');

        // Количество (в метрах или м²)
        $table->decimal('quantity', 12, 4);

        // Комментарий (например: "Поставка от поставщика Х", "Списано на заказ №45")
        $table->string('description')->nullable();

        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_histories');
    }
};
