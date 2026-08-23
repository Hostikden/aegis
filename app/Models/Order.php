<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'status',
        'deadline',
        'product_id',     // Переведено в nullable для поддержки многопозиционности
        'total_quantity', // Переведено в nullable для поддержки многопозиционности
    ];

    /**
     * Даты, которые Eloquent должен автоматически преобразовывать в объекты Carbon
     */
    protected $casts = [
        'deadline' => 'date',
    ];

    /**
     * Хуки жизненного цикла модели заказа (Автовозврат материалов на склад при удалении)
     */
    protected static function booted(): void
    {
        static::deleting(function (Order $order) {
            // Если заказ удаляется в статусе "В очереди" или "В производстве"
            if (in_array($order->status, ['pending', 'in_progress'])) {
                // Вызываем метод сервиса для безопасного уменьшения колонки reserved на складе
                if (class_exists(\App\Services\ProductionService::class)) {
                    app(\App\Services\ProductionService::class)->cancelReservationForOrder($order);
                }
            }
        });
    }
    /**
     * Связь: Позиции (изделия и количества) внутри данного комплексного заказа
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    /**
     * Связь: Технологические задачи / этапы выполнения в цеху по этому заказу
     */
    public function productionTasks(): HasMany
    {
        return $this->hasMany(ProductionTask::class, 'order_id');
    }

    /**
     * Старая связь: Оставлена для обратной совместимости системных вызовов
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
} // Конец класса Order
