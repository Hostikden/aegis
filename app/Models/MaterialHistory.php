<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id',
        'type',
        'quantity',
        'description',
    ];

    // ИСПРАВЛЕНО: без явного каста decimal(12,4) из БД приходил строкой
    // вида "10.0000", из-за чего колонка "Объем" в истории движения материала
    // показывала лишние нули. См. аналогичный фикс в App\Models\Material.
    protected $casts = [
        'quantity' => 'float',
    ];

    /**
     * Связь: К какому конкретно материалу на складе относится эта операция движения
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
