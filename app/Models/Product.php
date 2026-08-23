<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    // Разрешаем сохранение полей
    protected $fillable = ['sku', 'name', 'type'];

    /**
     * СВЯЗЬ С МАТЕРИАЛАМИ (Этого метода, скорее всего, сейчас нет в файле)
     */
    public function productMaterials(): HasMany
    {
        return $this->hasMany(ProductMaterial::class, 'product_id');
    }

    /**
     * СВЯЗЬ С КОМПОНЕНТАМИ СБОРКИ
     */
public function components(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
{
    // Меняем последний аргумент с 'product_id' на 'child_id'
    return $this->belongsToMany(
        Product::class,
        'product_components',
        'parent_id',
        'child_id' // <--- Указываем вашу реальную колонку из БД
    )
    ->withPivot('quantity')
    ->withTimestamps();
}


    /**
     * ОБРАТНАЯ СВЯЗЬ ДЛЯ СБОРОК
     */
    public function parentAssemblies(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_components',
            'product_id',
            'parent_id'
        )
        ->withPivot('quantity')
        ->withTimestamps();
    }

    public function operations(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    // Сортируем операции по номеру (10, 20, 30...), чтобы они всегда шли по порядку
    return $this->hasMany(ProductOperation::class)->orderBy('operation_number');
}


}
