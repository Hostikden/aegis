<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'operation_name',
        'status',
        'quantity_to_do',
    ];

    /**
     * Связь: К какому заказу на производство относится этот этап
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
