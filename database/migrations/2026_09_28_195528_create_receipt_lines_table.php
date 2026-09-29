<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_id')->constrained('receipts')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials');

            // Заявленный вес — то, что написано в накладной поставщика/заказчика.
            $table->decimal('declared_weight_kg', 10, 2);
            // Фактический вес — то, что показали весы при приёмке. Пока не введён,
            // считаем, что верим накладной (используем declared_weight_kg).
            $table->decimal('actual_weight_kg', 10, 2)->nullable();

            // Для прутка/трубы — замеренная длина одного хлыста (для проверки
            // соответствия теоретического и фактического веса).
            $table->decimal('measured_length_m', 8, 3)->nullable();
            $table->integer('pieces_count')->nullable(); // количество хлыстов/листов

            // Реквизиты прослеживаемости по ГОСТ 7566: номер плавки, номер партии
            // от поставщика, номер и дата документа о качестве.
            $table->string('melt_number')->nullable();       // номер плавки
            $table->string('supplier_lot_number')->nullable(); // номер партии поставщика
            $table->string('certificate_number')->nullable();  // номер сертификата/документа о качестве
            $table->date('certificate_date')->nullable();
            $table->boolean('certificate_attached')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_lines');
    }
};
