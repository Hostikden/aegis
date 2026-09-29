<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialLot extends Model
{
    use HasFactory;

    protected $fillable = [
        'lot_number',
        'material_id',
        'receipt_line_id',
        'melt_number',
        'certificate_number',
        'weight_kg',
        'remaining_weight_kg',
        'native_quantity',
        'remaining_native_quantity',
        'native_unit',
        'status',
        'received_at',
    ];

    protected $casts = [
        'weight_kg' => 'float',
        'remaining_weight_kg' => 'float',
        'native_quantity' => 'float',
        'remaining_native_quantity' => 'float',
        'received_at' => 'date',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(ReceiptLine::class);
    }

    /**
     * Следующий свободный номер партии — "ПАРТ-000001". Партии создаются
     * только программно при проведении поступления, ручного ввода номера
     * здесь нет, поэтому простого count()+1 достаточно.
     */
    public static function generateNextLotNumber(): string
    {
        return 'ПАРТ-' . str_pad(static::count() + 1, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Списать часть партии (в родной единице материала — метры/м²/шт).
     * Пропорционально уменьшает остаток веса в кг, чтобы вес и метраж партии
     * всегда оставались согласованы друг с другом.
     */
    public function debitNativeQuantity(float $nativeQtyToDebit): void
    {
        if ($this->remaining_native_quantity <= 0) {
            return;
        }

        $ratio = $this->remaining_weight_kg / $this->remaining_native_quantity;

        $this->remaining_native_quantity = max(0, $this->remaining_native_quantity - $nativeQtyToDebit);
        $this->remaining_weight_kg = max(0, $this->remaining_weight_kg - ($nativeQtyToDebit * $ratio));

        if ($this->remaining_native_quantity <= 0.0001) {
            $this->status = 'depleted';
        }

        $this->save();
    }
}
