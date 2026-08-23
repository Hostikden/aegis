<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = ['order_number', 'product_id', 'total_quantity', 'status', 'deadline'];

    // Какое изделие производится в заказе
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // Задачи по этапам для этого заказа
    public function productionTasks(): HasMany
    {
        return $this->hasMany(ProductionTask::class);
    }
}
