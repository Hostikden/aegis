<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOperation extends Model
{
    // Разрешаем запись новых технологических полей времени
    protected $fillable = [
        'product_id',
        'operation_number',
        'operation_name',
        'piece_time',
        'prep_time',
        'description',
        'comment'
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
