<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            // Позиция задачи в очереди конкретного типа оборудования (Фрезерная,
            // Токарная и т.д.). Своя независимая нумерация внутри каждого
            // equipment_type — то есть очередь фрезерных станков и очередь
            // токарных станков сортируются друг от друга независимо.
            // Nullable — у задач без техпроцесса (equipment_type = null)
            // очерёдность не имеет смысла.
            $table->integer('queue_position')->nullable()->after('equipment_type');
        });
    }

    public function down(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            $table->dropColumn('queue_position');
        });
    }
};
