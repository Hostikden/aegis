<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_debits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->foreignId('product_id')->constrained('products'); // конкретная деталь, на которую ушёл металл

            // Nullable — если партий не хватило (старый остаток без партий) и
            // недостающее количество списалось "напрямую", без привязки к плавке.
            $table->foreignId('material_lot_id')->nullable()->constrained('material_lots')->nullOnDelete();

            $table->decimal('quantity', 12, 4); // в родной единице материала (м/м²/шт)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_debits');
    }
};
