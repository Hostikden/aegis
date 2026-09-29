<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SteelGrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'gost',
        'density_kg_m3',
    ];

    protected $casts = [
        'density_kg_m3' => 'float',
    ];

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    /**
     * Теоретическая масса погонного метра прутка данного диаметра (кг/м).
     * masса = площадь сечения (м²) * плотность (кг/м³)
     */
    public function calculateRodWeightPerMeter(float $diameterMm): float
    {
        $radiusM = ($diameterMm / 1000) / 2;
        $areaM2 = M_PI * ($radiusM ** 2);

        return $areaM2 * $this->density_kg_m3;
    }

    /**
     * Теоретическая масса погонного метра трубы данного наружного диаметра
     * и толщины стенки (кг/м). Труба — кольцо, поэтому площадь сечения
     * считается как разница площадей наружного и внутреннего кругов.
     */
    public function calculatePipeWeightPerMeter(float $outerDiameterMm, float $wallThicknessMm): float
    {
        $outerRadiusM = ($outerDiameterMm / 1000) / 2;
        $innerRadiusM = $outerRadiusM - ($wallThicknessMm / 1000);

        $areaM2 = M_PI * (($outerRadiusM ** 2) - max(0, $innerRadiusM ** 2));

        return $areaM2 * $this->density_kg_m3;
    }

    /**
     * Теоретическая масса 1 м² плиты данной толщины (кг/м²).
     */
    public function calculatePlateWeightPerSquareMeter(float $thicknessMm): float
    {
        return ($thicknessMm / 1000) * $this->density_kg_m3;
    }
}
