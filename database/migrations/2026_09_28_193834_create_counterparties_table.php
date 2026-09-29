<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counterparties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['customer', 'supplier', 'both'])->default('customer');
            $table->string('inn')->nullable(); // ИНН — для сверки с документами о качестве (ГОСТ 7566)
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            // Задел под будущий обмен с бухгалтерией/1С — сопоставление контрагента
            // с его карточкой в учётной системе.
            $table->string('code_1c')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counterparties');
    }
};
