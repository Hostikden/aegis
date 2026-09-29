<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            // Сквозной номер документа — как в 1С, для внутренней нумерации
            // (не путать с номером накладной поставщика, он в document_number).
            $table->string('receipt_number')->unique();
            $table->foreignId('counterparty_id')->constrained('counterparties');
            $table->date('receipt_date');
            $table->string('document_number')->nullable(); // № накладной/УПД поставщика
            $table->date('document_date')->nullable();

            // Черновик — можно редактировать. Проведён — партии на складе уже
            // созданы, документ по правилам 1С больше не редактируется
            // (исправление только через сторно, добавим отдельным действием позже).
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
