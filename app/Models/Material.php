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
