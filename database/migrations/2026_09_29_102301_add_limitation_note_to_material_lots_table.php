<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_lots', function (Blueprint $table) {
            // Заполняется, если решение по входному контролю было "Принять с
            // ограничением" — например, с согласия заказчика при небольшом
            // отклонении геометрии.
            $table->text('limitation_note')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('material_lots', function (Blueprint $table) {
            $table->dropColumn('limitation_note');
        });
    }
};
