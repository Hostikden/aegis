<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            // Добавляем уникальный номер Item для идентификации чертежей в цеху
            $table->integer('item_number')->nullable()->after('order_id');
        });
    }

    public function down(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            $table->dropColumn('item_number');
        });
    }
};
