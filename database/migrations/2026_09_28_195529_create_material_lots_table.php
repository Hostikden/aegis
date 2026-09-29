<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_lots', function (Blueprint $table) {
            $table->id();
            $table->string('lot_number')->unique(); // Внутренний номер партии, например "ПАРТ-000123"
            $table->foreignId('material_id')->constrained('materials');

            // Партия появилась из документа поступления — nullable, потому что
            // существующие остатки на момент запуска v2 заводятся отдельным
            // "Вводом начальных остатков" без исходного поступления.
            $table->foreignId('receipt_line_id')->nullable()->constrained('receipt_lines')->nullOnDelete();

            // Прослеживаемость — дублируем из receipt_line, чтобы партия была
            // самодостаточной даже если документ поступления когда-то удалят.
            $table->string('melt_number')->nullable();
            $table->string('certificate_number')->nullable();

            // Вес партии в кг — как её видит заказчик/бухгалтерия.
            $table->decimal('weight_kg', 10, 2);
            $table->decimal('remaining_weight_kg', 10, 2); // Остаток веса (уменьшается при списании)

            // То же самое количество, но в единице, которой оперирует цех
            // (метры для прутка/трубы, м² для плиты, шт для покупных изделий) —
            // посчитано через плотность марки на момент поступления.
            $table->decimal('native_quantity', 12, 4);
            $table->decimal('remaining_native_quantity', 12, 4);
            $table->string('native_unit', 10); // 'м' | 'м²' | 'шт'

            // available — партия на складе и доступна для резерва;
            // quarantine — ждёт решения по входному контролю (Этап 3);
            // rejected — забракована, в резерв/производство не идёт;
            // depleted — остаток исчерпан (для истории, не удаляем).
            $table->enum('status', ['available', 'quarantine', 'rejected', 'depleted'])->default('available');

            $table->date('received_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_lots');
    }
};
