<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_lines', function (Blueprint $table) {
            // Итоговое решение по строке — назначается ПОСЛЕ заполнения всех
            // пунктов проверки (inspection_results). Пока null — строка считается
            // непройденной, и документ поступления нельзя провести.
            $table->enum('decision', ['accept', 'accept_with_limitation', 'quarantine', 'return'])
                ->nullable()->after('notes');
            $table->text('decision_comment')->nullable()->after('decision');
            $table->foreignId('inspected_by')->nullable()->after('decision_comment')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable()->after('inspected_by');
        });
    }

    public function down(): void
    {
        Schema::table('receipt_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inspected_by');
            $table->dropColumn(['decision', 'decision_comment', 'inspected_at']);
        });
    }
};
