<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            // Поле "Длина единицы / хлыста (м)" (для прутка/трубы) и
            // "Длина плиты (мм)" (для плиты) — фактически нигде не используется:
            // ни в расчёте требуемых материалов (ProductionService::calculateRequiredMaterials
            // работает с consumption_rate в метрах/м² напрямую), ни в списании со
            // склада (Material::quantity — это сразу остаток в метрах/м², без
            // деления на "хлысты"), ни в таблице списка материалов. Удаляем как
            // мёртвое поле.
            $table->dropColumn('length');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('length', 10, 2)->nullable();
        });
    }
};
