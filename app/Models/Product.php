<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'type',
    ];

    /**
     * Связь: Из каких деталей состоит данная сборка
     */
    /**
     * Связь: Из каких деталей состоит данная сборка (Спецификация узлов)
     */
    public function components(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_components',
            'parent_id',
            'child_id' // ЖЕСТКО ЗАФИКСИРОВАНО: имя колонки из вашей локальной базы данных
        )
        ->withPivot('quantity')
        ->withTimestamps();
    }


    /**
     * Связь: Нормы расхода сырья и комплектующих (BOM) для детали
     */
    public function productMaterials(): HasMany
    {
        return $this->hasMany(ProductMaterial::class, 'product_id');
    }

    /**
     * Связь: Маршрутная карта (Техпроцесс) обработки детали
     */
    public function operations(): HasMany
    {
        // Сортируем по номеру, чтобы операции всегда шли в правильном порядке: 10, 20, 30...
        return $this->hasMany(ProductOperation::class, 'product_id')->orderBy('operation_number');
    }
}
