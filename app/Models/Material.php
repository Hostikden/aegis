<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasFactory;

    // Добавьте 'diameter' и 'length' в этот массив
    protected $fillable = [
        'name',
        'grade',
        'thickness',
        'size',
        'quantity',
        'unit',
            'width',
        'diameter',  // <--- Разрешаем сохранение диаметра
        'length',    // <--- Разрешаем сохранение длины хлыста
    ];

    public function history(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(MaterialHistory::class)->latest(); // Свежие записи будут сверху
}

}
