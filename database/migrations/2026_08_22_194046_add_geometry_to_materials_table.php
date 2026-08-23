<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('diameter', 8, 2)->nullable()->after('thickness'); // Диаметр в мм
            $table->decimal('length', 8, 2)->nullable()->after('size'); // Длина хлыста в метрах
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['diameter', 'length']);
        });
    }
};
