<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            $table->string('equipment_type')->nullable()->after('operation_name');
            $table->decimal('planned_minutes', 10, 2)->default(0)->after('quantity_to_do');
            $table->timestamp('started_at')->nullable()->after('status');
            $table->timestamp('completed_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            $table->dropColumn(['equipment_type', 'planned_minutes', 'started_at', 'completed_at']);
        });
    }
}
