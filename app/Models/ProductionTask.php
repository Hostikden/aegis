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
        // ИСПРАВЛЕНО: колонки существуют в БД (миграция
        // add_equipment_load_fields_to_production_tasks_table), но не были
        // в $fillable — CreateOrder::generateTasksForProduct() пытался их
        // записать при создании задачи, но Laravel тихо отбрасывал эти поля
        // при массовом create(), и отчёт по загрузке оборудования всегда
        // получал equipment_type = null / planned_minutes = 0.
        'equipment_type',
        'planned_minutes',
        'started_at',
        'completed_at',
        'queue_position',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'planned_minutes' => 'float',
    ];

    /**
     * Связь: К какому заказу на производство относится этот этап
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

}
