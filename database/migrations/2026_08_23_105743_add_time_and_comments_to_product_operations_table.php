<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_operations', function (Blueprint $table) {
            // Тшт — Штучное время на обработку 1 детали (в минутах)
            $table->decimal('piece_time', 8, 2)->default(0.00)->after('operation_name');
            // Тпз — Подготовительно-заключительное время на всю партию / наладку (в минутах)
            $table->decimal('prep_time', 8, 2)->default(0.00)->after('piece_time');
            // Технологические комментарии / Примечания к операции
            $table->text('comment')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('product_operations', function (Blueprint $table) {
            $table->dropColumn(['piece_time', 'prep_time', 'comment']);
        });
    }
};
