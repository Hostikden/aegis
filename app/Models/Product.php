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
        'drawing_files', // Разрешили массовую запись массива чертежей
    ];

    /**
     * Автоматическое преобразование массива файлов чертежей в JSON при записи в БД
     */
    protected $casts = [
        'drawing_files' => 'array',
    ];

    /**
     * Связь: Материалы и комплектующие детали (BOM спецификация)
     */
    public function productMaterials(): HasMany
    {
        return $this->hasMany(ProductMaterial::class);
    }
    /**
     * Связь: Маршрутная технологическая карта (Операции детали)
     */
    public function operations(): HasMany
    {
        return $this->hasMany(ProductOperation::class)->orderBy('operation_number', 'asc');
    }

    /**
     * Связь: Входящие компоненты (Если данное изделие является Сборкой)
     */
    public function components(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_components', // Имя промежуточной таблицы связей
            'parent_id',          // Внешний ключ родительского узла
            'child_id'            // Внешний ключ вложенной детали
        )->withPivot('quantity')->withTimestamps();
    }
} // Конец класса Product
