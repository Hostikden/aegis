<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_materials', function (Blueprint $table) {
            // Добавляем поля для хранения заготовки чертежа
            $table->string('material_type')->nullable()->after('product_id');
            $table->string('material_grade')->nullable()->after('material_type');
            $table->float('detail_length', 10, 2)->nullable()->after('material_id');
            $table->float('detail_width', 10, 2)->nullable()->after('detail_length');
        });
    }

    public function down(): void
    {
        Schema::table('product_materials', function (Blueprint $table) {
            $table->dropColumn(['material_type', 'material_grade', 'detail_length', 'detail_width']);
        });
    }
};
