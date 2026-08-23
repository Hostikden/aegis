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
    Schema::create('product_components', function (Blueprint $table) {
        $table->id();
        // ID готового изделия (Сборки)
        $table->foreignId('parent_id')->constrained('products')->cascadeOnDelete();
        // ID вложенного изделия (Детали или другой подсборки)
        $table->foreignId('child_id')->constrained('products')->cascadeOnDelete();
        // Сколько штук этой детали нужно на 1 единицу сборки
        $table->integer('quantity')->default(1);
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_components');
    }
};
