<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::create('orders', function (Blueprint $table) {
        $table->id();
        $table->string('order_number')->unique(); // Номер заказа (например: ЗНП-001)
        $table->foreignId('product_id')->constrained()->cascadeOnDelete(); // Какое изделие делаем
        $table->integer('total_quantity'); // Сколько штук заказано
        $table->enum('status', ['pending', 'in_progress', 'completed', 'shipped'])->default('pending'); // Статус
        $table->date('deadline'); // Срок сдачи партии
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
