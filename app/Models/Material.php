<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'grade',

        'diameter',
        'thickness',
        'width',
        'quantity',
        'reserved', // <-- ДОБАВИЛИ РЕЗЕРВ
        'unit',
    ];

    // ИСПРАВЛЕНО: без явного каста decimal(12,3)/decimal(12,3) из БД приходили
    // строками вида "10.000", из-за чего колонка "Всего на складе" в списке
    // материалов показывала лишние нули, в отличие от "Доступно" (которая
    // считается на лету через вычитание и поэтому уже была "чистым" числом).
    // Каст к float убирает эту разницу во всех местах разом — и в таблице, и в форме.
    protected $casts = [
        'quantity' => 'float',
        'reserved' => 'float',
    ];

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
}
