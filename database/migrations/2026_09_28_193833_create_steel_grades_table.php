<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('steel_grades', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Например: 12Х18Н10Т, 09Г2С, Ст3
            $table->string('gost')->nullable(); // ГОСТ на марку, например "ГОСТ 5632-2014"
            // Плотность в кг/м³ — ключевое поле для пересчёта веса (кг) в длину (м)
            // и обратно: масса = объём * плотность. Для нержавейки 12Х18Н10Т ≈ 7900,
            // для стали Ст3 ≈ 7850. Редактируется технологом при необходимости уточнить.
            $table->decimal('density_kg_m3', 8, 1)->default(7850);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('steel_grades');
    }
};
