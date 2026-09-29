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
    ];

    protected $casts = [
        'declared_weight_kg' => 'float',
        'actual_weight_kg' => 'float',
        'measured_length_m' => 'float',
        'certificate_date' => 'date',
        'certificate_attached' => 'boolean',
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
