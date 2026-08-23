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
    Schema::create('production_tasks', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id')->constrained()->cascadeOnDelete();
        $table->string('operation_name'); // Название этапа (Лазер, Гибка, Сварка)
        $table->integer('quantity_to_do'); // Сколько деталей нужно сделать
        $table->integer('quantity_done')->default(0); // Сколько уже сделано по факту
        $table->enum('status', ['waiting', 'active', 'finished'])->default('waiting');
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_tasks');
    }
};
