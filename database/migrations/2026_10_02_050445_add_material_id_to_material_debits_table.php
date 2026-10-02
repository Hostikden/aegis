<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('material_debits', function (Blueprint $table) {
            // ИСПРАВЛЕНО: материал раньше был виден только через material_lot_id,
            // а для списаний "без партии" (material_lot_id = null — например,
            // старый остаток, на который ещё не завели начальную партию)
            // материал было невозможно определить вообще. Теперь material_id
            // всегда заполнен, независимо от того, есть партия или нет.
            $table->foreignId('material_id')->nullable()->after('product_id')->constrained('materials');
        });

        // Backfill для уже существующих записей (если на момент накатки этой
        // миграции в material_debits уже что-то есть) — подтягиваем material_id
        // из связанной партии, где это возможно.
        DB::statement('
            UPDATE material_debits
            JOIN material_lots ON material_lots.id = material_debits.material_lot_id
            SET material_debits.material_id = material_lots.material_id
            WHERE material_debits.material_lot_id IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('material_debits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('material_id');
        });
    }
};
