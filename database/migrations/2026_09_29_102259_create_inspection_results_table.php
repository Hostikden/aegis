<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('receipt_line_id')->constrained('receipt_lines')->cascadeOnDelete();

            // Фиксированный набор пунктов проверки — соответствует разделам акта
            // входного контроля по ГОСТ 24297 (документы/количество/геометрия)
            // и обязательным пунктам сверки с сертификатом по ГОСТ 7566 (поверхность).
            $table->enum('checkpoint', ['documents', 'quantity', 'geometry', 'surface']);

            $table->enum('result', ['pass', 'fail']);
            $table->string('measured_value')->nullable(); // например, замеренный диаметр
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['receipt_line_id', 'checkpoint']); // один результат на пункт по строке
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_results');
    }
};
