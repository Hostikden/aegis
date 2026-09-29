<?php

namespace Database\Seeders;

use App\Models\SteelGrade;
use Illuminate\Database\Seeder;

/**
 * Базовый набор марок стали для входного контроля. Плотность (кг/м³) взята
 * по справочным данным ГОСТ на соответствующие марки — можно уточнить прямо
 * в интерфейсе на карточке марки, если у вас другие справочные значения.
 */
class SteelGradeSeeder extends Seeder
{
    public function run(): void
    {
        $grades = [
            ['name' => 'Ст3', 'gost' => 'ГОСТ 380-2005', 'density_kg_m3' => 7850],
            ['name' => '09Г2С', 'gost' => 'ГОСТ 19281-2014', 'density_kg_m3' => 7850],
            ['name' => '40Х', 'gost' => 'ГОСТ 4543-2016', 'density_kg_m3' => 7820],
            ['name' => '45', 'gost' => 'ГОСТ 1050-2013', 'density_kg_m3' => 7826],
            ['name' => '12Х18Н10Т', 'gost' => 'ГОСТ 5632-2014', 'density_kg_m3' => 7900],
            ['name' => '08Х18Н10', 'gost' => 'ГОСТ 5632-2014', 'density_kg_m3' => 7900],
            ['name' => 'AISI 304', 'gost' => 'ISO 15510', 'density_kg_m3' => 8000],
            ['name' => 'Д16Т (алюминий)', 'gost' => 'ГОСТ 4784-97', 'density_kg_m3' => 2780],
            ['name' => 'АМг6', 'gost' => 'ГОСТ 4784-97', 'density_kg_m3' => 2640],
            ['name' => 'Бронза БрАЖ9-4', 'gost' => 'ГОСТ 18175-78', 'density_kg_m3' => 7500],
            ['name' => 'Латунь ЛС59-1', 'gost' => 'ГОСТ 15527-2004', 'density_kg_m3' => 8500],
        ];

        foreach ($grades as $grade) {
            SteelGrade::firstOrCreate(['name' => $grade['name']], $grade);
        }
    }
}
