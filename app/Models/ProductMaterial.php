<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMaterial extends Model
{
protected $fillable = [
    'product_id',
    'material_id',
    'consumption_rate',
    // Добавляем служебные поля, чтобы Filament мог сохранять состояние формы
    'material_type',
    'material_grade',
    'detail_length',
    'detail_width',
    'allowance_factor',
];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
