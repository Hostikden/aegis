<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReceiptLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_id',
        'material_id',
        'declared_weight_kg',
        'actual_weight_kg',
        'measured_length_m',
        'pieces_count',
        'melt_number',
        'supplier_lot_number',
        'certificate_number',
        'certificate_date',
        'certificate_attached',
        'notes',
        'decision',
        'decision_comment',
        'inspected_by',
        'inspected_at',
    ];

    protected $casts = [
        'declared_weight_kg' => 'float',
        'actual_weight_kg' => 'float',
        'measured_length_m' => 'float',
        'certificate_date' => 'date',
        'certificate_attached' => 'boolean',
        'inspected_at' => 'datetime',
    ];

    public const DECISIONS = [
        'accept' => '✅ Принять',
        'accept_with_limitation' => '⚠️ Принять с ограничением',
        'quarantine' => '⏳ Карантин до решения',
        'return' => '↩️ Вернуть поставщику',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function lot(): HasOne
    {
        return $this->hasOne(MaterialLot::class);
    }

    public function inspectionResults(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InspectionResult::class);
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    /**
     * Пройдены ли ВСЕ пункты входного контроля (по одному результату на
     * каждый пункт из InspectionResult::CHECKPOINTS).
     */
    public function isFullyInspected(): bool
    {
        return $this->inspectionResults()->count() === count(InspectionResult::CHECKPOINTS);
    }

    /**
     * Есть ли хотя бы один непройденный пункт проверки.
     */
    public function hasFailedCheckpoint(): bool
    {
        return $this->inspectionResults()->where('result', 'fail')->exists();
    }

    /**
     * Вес, который будет принят на склад: фактический (взвешенный), если
     * введён, иначе — заявленный по накладной.
     */
    public function getEffectiveWeightKgAttribute(): float
    {
        return $this->actual_weight_kg ?? $this->declared_weight_kg;
    }

    /**
     * Расхождение фактического веса с заявленным, в процентах. Пригодится на
     * Этапе 3 как один из автоматических пунктов проверки входного контроля.
     */
    public function getWeightDiscrepancyPercentAttribute(): ?float
    {
        if (!$this->actual_weight_kg || $this->declared_weight_kg <= 0) {
            return null;
        }

        return round((($this->actual_weight_kg - $this->declared_weight_kg) / $this->declared_weight_kg) * 100, 2);
    }
}
