<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_line_id',
        'checkpoint',
        'result',
        'measured_value',
        'comment',
    ];

    public const CHECKPOINTS = [
        'documents' => 'Документы (сертификат, штамп ОТК, соответствие марки)',
        'quantity' => 'Количество / вес',
        'geometry' => 'Геометрия (диаметр / толщина)',
        'surface' => 'Внешний вид / поверхность',
    ];

    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(ReceiptLine::class);
    }
}
