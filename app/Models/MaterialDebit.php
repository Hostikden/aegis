<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Запись о том, с какой именно партии (плавки) и сколько было списано под
 * конкретную деталь конкретного заказа. Это и есть прослеживаемость "из
 * какого металла сделана эта деталь" — используется на печати паспорта.
 */
class MaterialDebit extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'material_lot_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'material_lot_id');
    }
}
