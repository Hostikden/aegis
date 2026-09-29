<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            // Связь с справочником марок стали — нужна, чтобы пересчитывать кг в метры
            // через плотность. Nullable и necoercive: существующие материалы (где
            // "grade" — просто текстовая строка) продолжат работать как раньше,
            // пока их вручную не свяжут с конкретной маркой из справочника.
            $table->foreignId('steel_grade_id')->nullable()->after('grade')
                ->constrained('steel_grades')->nullOnDelete();

            // Толщина стенки — нужна только для типа "Труба", чтобы считать массу
            // погонного метра как: π * s * (D - s) * плотность. Раньше в форме
            // для трубы указывался только диаметр, что для расчёта веса трубы
            // недостаточно (труба — это кольцо, а не сплошной круг).
            $table->decimal('wall_thickness', 8, 2)->nullable()->after('diameter');

            // Задел под будущий обмен с бухгалтерией/1С.
            $table->string('code_1c')->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('steel_grade_id');
            $table->dropColumn(['wall_thickness', 'code_1c']);
        });
    }
};
