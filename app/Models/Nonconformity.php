<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nonconformity extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'material_lot_id',
        'receipt_line_id',
        'category',
        'ntd_reference',
        'description',
        'affected_weight_kg',
        'photos',
        'decision',
        'status',
        'created_by',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'photos' => 'array',
        'affected_weight_kg' => 'float',
        'resolved_at' => 'datetime',
    ];

    public const CATEGORIES = [
        'documents' => 'Документы (сертификат, маркировка)',
        'quantity' => 'Количество / вес',
        'geometry' => 'Геометрия',
        'surface' => 'Поверхность / внешний вид',
        'other' => 'Прочее',
    ];

    public const DECISIONS = [
        'return' => 'Вернуть поставщику',
        'accept_with_consent' => 'Принять с согласия заказчика',
        'rework' => 'Переработать / исправить',
        'dispose' => 'Утилизировать',
    ];

    public const STATUSES = [
        'open' => '🔴 Открыт',
        'pending_customer' => '🟡 Согласование с заказчиком',
        'closed' => '✅ Закрыт',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(MaterialLot::class, 'material_lot_id');
    }

    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(ReceiptLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public static function generateNextNumber(): string
    {
        return 'НС-' . str_pad(static::count() + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Материал, к которому относится акт — через партию либо через строку
     * поступления, в зависимости от того, что заполнено.
     */
    public function getMaterialAttribute(): ?Material
    {
        return $this->lot?->material ?? $this->receiptLine?->material;
    }
}
