<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMaterial extends Model
{
    use HasFactory;

    // Разрешаем сохранение всех технологических параметров заготовки в базу данных
    protected $fillable = [
        'product_id',
        'material_id',
        'consumption_rate',
        'material_type',
        'material_grade',
        'detail_length',
        'detail_width',
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
