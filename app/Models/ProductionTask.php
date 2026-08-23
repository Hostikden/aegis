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

        protected static function booted(): void
    {
        static::updated(function (ProductionTask $task) {
            // Если статус операции изменился на "completed" (Выполнен)
            if ($task->isDirty('status') && $task->status === 'completed') {

                // Проверяем, является ли эта операция Заготовительной
                // Ищем вхождение слова "Заготовительная" в названии операции
                if (str_contains($task->operation_name, '[Заготовительная]')) {
                    $order = $task->order;

                    if ($order) {
                        // Запускаем снятие с резерва и физическое уменьшение остатка метров/штук
                        app(\App\Services\ProductionService::class)->debitMaterialsFromReserve($order);
                    }
                }
            }
        });
    }

}
