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
        'item_number', // <-- РАЗРЕШИЛИ ЗАПИСЬ НОВОГО НОМЕРА ITEM
        'operation_name',
        'status',
        'quantity_to_do',
        // ИСПРАВЛЕНО: эти колонки существуют в БД (миграция
        // add_operator_fields_to_production_tasks_table), но не были
        // в $fillable — из-за этого Widgets\OperatorTasks::finish_task()
        // и ::start_task() молча теряли operator_id/quantity_done/
        // quantity_scrapped при массовом update() без единой ошибки.
        'operator_id',
        'quantity_done',
        'quantity_scrapped',
    ];

    /**
     * Связь: К какому заказу на производство относится этот этап
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * ИСПРАВЛЕНО: связь отсутствовала полностью, хотя колонка operator_id
     * в БД есть (внешний ключ на users), а Widgets\OperatorTasks обращался
     * к $record->operator->name — без этого метода это падало с
     * "Call to undefined method ProductionTask::operator()".
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
