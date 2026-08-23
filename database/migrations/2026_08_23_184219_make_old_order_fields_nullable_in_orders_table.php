<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Разрешаем null для старых одиночных полей, так как данные теперь хранятся в order_items
            $table->foreignId('product_id')->nullable()->change();
            $table->integer('total_quantity')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('product_id')->change();
            $table->integer('total_quantity')->change();
        });
    }
};
