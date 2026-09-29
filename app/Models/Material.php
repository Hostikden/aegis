<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'grade',
        'steel_grade_id', // Связь со справочником марок — для пересчёта кг ↔ метры
        'diameter',
        'wall_thickness', // Только для типа "Труба"
        'thickness',
        'width',
        'quantity',
        'reserved', // <-- ДОБАВИЛИ РЕЗЕРВ
        'unit',
        'code_1c',
    ];

    // ИСПРАВЛЕНО: без явного каста decimal(12,3)/decimal(12,3) из БД приходили
    // строками вида "10.000", из-за чего колонка "Всего на складе" в списке
    // материалов показывала лишние нули, в отличие от "Доступно" (которая
    // считается на лету через вычитание и поэтому уже была "чистым" числом).
    // Каст к float убирает эту разницу во всех местах разом — и в таблице, и в форме.
    protected $casts = [
        'quantity' => 'float',
        'reserved' => 'float',
        'diameter' => 'float',
        'wall_thickness' => 'float',
        'thickness' => 'float',
        'width' => 'float',
    ];

    public function steelGrade(): BelongsTo
    {
        return $this->belongsTo(SteelGrade::class);
    }

    /**
     * Рассчитать чистый свободный остаток проката на складе (за вычетом брони)
     */
    public function getAvailableQuantityAttribute(): float
    {
        return max(0, $this->quantity - $this->reserved);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(MaterialHistory::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(MaterialLot::class);
    }

    /**
     * Теоретическая масса ЕДИНИЦЫ УЧЁТА материала в кг — то есть кг на 1 метр
     * (для прутка/трубы) или кг на 1 м² (для плиты). Это и есть мост между
     * тем, как заказчик присылает материал (в кг), и тем, как цех его
     * расходует (в метрах/м²). Требует привязанной марки стали
     * (steel_grade_id) — без неё пересчитать вес невозможно, возвращает null.
     *
     * Пример: пруток 12Х18Н10Т Ø20 весит ≈2,47 кг на метр, тот же пруток
     * Ø60 — уже ≈22,3 кг на метр, при одинаковой длине разница почти в 9 раз.
     */
    public function calculateTheoreticalWeightPerUnit(): ?float
    {
        if (!$this->steelGrade) {
            return null;
        }

        return match ($this->name) {
            'Пруток' => $this->diameter
                ? $this->steelGrade->calculateRodWeightPerMeter($this->diameter)
                : null,

            'Труба' => ($this->diameter && $this->wall_thickness)
                ? $this->steelGrade->calculatePipeWeightPerMeter($this->diameter, $this->wall_thickness)
                : null,

            'Плита' => $this->thickness
                ? $this->steelGrade->calculatePlateWeightPerSquareMeter($this->thickness)
                : null,

            default => null,
        };
    }
}

