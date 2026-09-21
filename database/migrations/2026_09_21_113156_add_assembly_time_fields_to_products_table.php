<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Время на финальную сборку узла — раньше нигде не задавалось и
            // planned_minutes для сборочной задачи всегда жёстко стояло 0
            // (см. ProductionService::generateTasksForProduct). Теперь технолог
            // может указать это время прямо на карточке сборки.
            $table->decimal('assembly_piece_time', 8, 2)->nullable()->after('type'); // Тшт на 1 узел, мин
            $table->decimal('assembly_prep_time', 8, 2)->nullable()->after('assembly_piece_time'); // Тпз на партию, мин
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['assembly_piece_time', 'assembly_prep_time']);
        });
    }
};
