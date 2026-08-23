<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            $table->integer('quantity_scrapped')->default(0)->after('quantity_done'); // Брак
            $table->foreignId('operator_id')->nullable()->after('status')->constrained('users')->nullOnDelete(); // Кто делал
        });
    }

    public function down(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            $table->dropForeign(['operator_id']);
            $table->dropColumn(['quantity_scrapped', 'operator_id']);
        });
    }
};
