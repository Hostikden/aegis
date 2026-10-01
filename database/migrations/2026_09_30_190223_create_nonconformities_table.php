<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nonconformities', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique(); // "НС-000001"

            // Привязка к партии (если дефект найден УЖЕ после приёмки — на
            // складе или в производстве) ИЛИ к строке поступления (если
            // забраковано прямо на входном контроле, решение "Вернуть
            // поставщику" — тогда партия вообще не создаётся). Хотя бы одно
            // из двух полей заполнено.
            $table->foreignId('material_lot_id')->nullable()->constrained('material_lots')->nullOnDelete();
            $table->foreignId('receipt_line_id')->nullable()->constrained('receipt_lines')->nullOnDelete();

            // Категория и основание — как того требует ГОСТ 24297 (причина
            // рекламации с указанием пункта ТУ/стандарта).
            $table->enum('category', ['documents', 'quantity', 'geometry', 'surface', 'other']);
            $table->string('ntd_reference')->nullable(); // пункт ГОСТ/ТУ, которому не соответствует
            $table->text('description');
            $table->decimal('affected_weight_kg', 10, 2)->nullable();
            $table->json('photos')->nullable();

            $table->enum('decision', ['return', 'accept_with_consent', 'rework', 'dispose'])->nullable();
            $table->enum('status', ['open', 'pending_customer', 'closed'])->default('open');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nonconformities');
    }
};
