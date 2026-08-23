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
    Schema::create('materials', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // Лист, Труба, Швеллер
        $table->string('grade'); // Марка стали: Ст3, AISI 304
        $table->decimal('thickness', 8, 2)->nullable(); // Толщина в мм
        $table->string('size')->nullable(); // 1500x3000, Ду50
        $table->decimal('quantity', 12, 3)->default(0); // Остаток
        $table->string('unit')->default('кг'); // Единица измерения
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
