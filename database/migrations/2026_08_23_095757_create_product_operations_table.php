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
    Schema::create('product_operations', function (Blueprint $table) {
        $table->id();
        // Привязка к детали
        $table->foreignId('product_id')->constrained()->cascadeOnDelete();

        // Номер операции (шаг 10: 10, 20, 30...)
        $table->integer('operation_number');

        // Название операции (наш жесткий список)
        $table->string('operation_name');

        // Описание перехода/работ (например: "Подрезать торец, точить Ø45")
        $table->text('description')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_operations');
    }
};
