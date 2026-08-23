<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'product_id',
        'total_quantity',
        'status',
        'deadline'
    ];

    // Приведение типов: deadline теперь всегда объект даты Carbon
    protected $casts = [
        'deadline' => 'date',
    ];


        /**
     * Связь: Позиции (изделия) внутри данного комплексного заказа
     */
    public function orderItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }


    /**
     * Связь: Какое изделие производится в заказе
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Связь: Задачи по технологическим этапам для этого заказа
     */
    public function productionTasks(): HasMany
    {
        return $this->hasMany(ProductionTask::class);
    }

        /**
     * Хуки жизненного цикла модели заказа
     */
    protected static function booted(): void
    {
        static::deleting(function (Order $order) {
            // Если заказ удаляется в статусе "В очереди" или "В работе" (когда резерв еще держится)
            if (in_array($order->status, ['pending', 'in_progress'])) {
                // Вызываем метод сервиса для безопасного уменьшения колонки reserved на складе
                if (class_exists(\App\Services\ProductionService::class)) {
                    app(\App\Services\ProductionService::class)->cancelReservationForOrder($order);
                }
            }
        });
    }

}
