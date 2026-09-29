<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_histories', function (Blueprint $table) {
            // Ручное списание (вкладка "История") теперь обязано списывать
            // остаток с конкретной партии, а не только с общего Material::quantity
            // — иначе сумма остатков по партиям постепенно расходится с
            // общим остатком материала. Nullable — для материалов, у которых
            // ещё нет ни одной партии (старый остаток из версии 1, пока не
            // заведён через "Завести партию по текущему остатку").
            $table->foreignId('material_lot_id')->nullable()->after('material_id')
                ->constrained('material_lots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('material_histories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_lot_id');
        });
    }
};
